<?php

namespace App\Portal\Actions;

use App\Enums\ErpSyncState;
use App\Enums\OrderOrigin;
use App\Enums\OrderStatus;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Portal\Orders\NormalizeOrderQuantity;
use App\Portal\Pricing\ResolveAccountProductPrice;
use App\Portal\Queries\VendorCatalogueQuery;
use App\Portal\VendorAccountContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitVendorOrder
{
    public function __construct(
        private VendorAccountContext $accountContext,
        private VendorCatalogueQuery $catalogueQuery,
        private ResolveAccountProductPrice $priceResolver,
        private NormalizeOrderQuantity $normalizeQuantity,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(User $user, string $submissionToken, ?string $customerReference, array $lines): SalesOrder
    {
        $account = $this->accountContext->resolve($user);

        if (! Str::isUuid($submissionToken)) {
            throw new DomainException('The submission token is invalid.');
        }

        if ($lines === [] || count($lines) > 100) {
            throw new DomainException('Add at least one product before submitting the order.');
        }

        if ($customerReference !== null && mb_strlen($customerReference) > 255) {
            throw new DomainException('The customer reference must not exceed 255 characters.');
        }

        $validatedLines = [];

        foreach ($lines as $line) {
            $quantity = $line['quantity'] ?? null;

            if (! isset($line['product_id'])
                || ! is_int($line['product_id'])
                || (! is_string($quantity) && ! is_int($quantity) && ! is_float($quantity))) {
                throw new DomainException('Every order line must contain a valid product and positive quantity.');
            }

            try {
                $normalizedQuantity = $this->normalizeQuantity->handle($quantity);
            } catch (DomainException) {
                throw new DomainException('Every order line must contain a valid product and positive quantity.');
            }

            if (! isset($line['price'], $line['currency'], $line['price_source'])
                || ! is_scalar($line['price'])
                || ! is_string($line['currency'])
                || ! is_string($line['price_source'])
                || (! isset($line['unit_of_measure']) && ! array_key_exists('unit_of_measure', $line))) {
                throw new DomainException('Review current server pricing before submitting the order.');
            }

            $validatedLines[] = [
                'product_id' => $line['product_id'],
                'quantity' => $normalizedQuantity,
                'price' => (string) $line['price'],
                'currency' => $line['currency'],
                'unit_of_measure' => (string) $line['unit_of_measure'],
                'price_source' => $line['price_source'],
            ];
        }

        if (collect($validatedLines)->pluck('product_id')->duplicates()->isNotEmpty()) {
            throw new DomainException('Each product may appear only once in an order.');
        }

        return Cache::lock("vendor-order-submission:{$submissionToken}", 15)->block(5, fn (): SalesOrder => DB::transaction(function () use ($account, $user, $submissionToken, $customerReference, $validatedLines): SalesOrder {
            $existingOrder = SalesOrder::query()
                ->where('portal_submission_token', $submissionToken)
                ->lockForUpdate()
                ->first();

            if ($existingOrder !== null) {
                if ($existingOrder->account_id !== $account->getKey()) {
                    throw new DomainException('The submission token is not valid for this account.');
                }

                return $existingOrder->load('lines.product');
            }

            $productIds = collect($validatedLines)->pluck('product_id')->unique()->values();
            $products = $this->catalogueQuery->forUser($user)
                ->whereKey($productIds)
                ->lockForUpdate()
                ->get(['id', 'sku', 'name', 'description', 'price', 'currency', 'unit_of_measure'])
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw new DomainException('One or more selected products are not available to this account.');
            }

            $accountPrices = AccountProductPrice::query()
                ->whereBelongsTo($account)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get(['id', 'account_id', 'product_id', 'amount', 'currency', 'unit_of_measure'])
                ->groupBy('product_id');

            foreach ($products as $product) {
                $product->setRelation('accountPrices', $accountPrices->get($product->getKey(), collect()));
            }

            $resolvedPrices = [];

            foreach ($validatedLines as $line) {
                /** @var Product $product */
                $product = $products->get($line['product_id']);
                $price = $this->priceResolver->resolve($account, $product);

                if ($price === null) {
                    throw new DomainException("Pricing is unavailable for product [{$product->sku}].");
                }

                if (! BigDecimal::of($price->amount)->isEqualTo(BigDecimal::of($line['price']))
                    || $price->currency !== $line['currency']
                    || $price->unitOfMeasure !== $line['unit_of_measure']
                    || $price->source->value !== $line['price_source']) {
                    throw new DomainException('Pricing changed after the draft was reviewed. Review the revised totals and confirm again.');
                }

                $resolvedPrices[$product->getKey()] = $price;
            }

            $currencies = collect($resolvedPrices)->pluck('currency')->unique();

            if ($currencies->count() !== 1) {
                throw new DomainException('All products in an order must use the same currency.');
            }

            $totalAmount = '0';

            foreach ($validatedLines as $line) {
                $price = $resolvedPrices[$line['product_id']];
                $lineTotal = BigDecimal::of($price->amount)
                    ->multipliedBy($line['quantity'])
                    ->toScale(2, RoundingMode::HalfUp);
                $totalAmount = (string) BigDecimal::of($totalAmount)->plus($lineTotal);
            }

            $order = SalesOrder::query()->create([
                'order_number' => 'CVO-'.now()->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', $submissionToken), 0, 10)),
                'order_date' => now(),
                'account_id' => $account->getKey(),
                'total_amount' => $totalAmount,
                'status' => OrderStatus::PENDING,
                'currency' => $currencies->first(),
                'origin' => OrderOrigin::PORTAL,
                'erp_sync_state' => ErpSyncState::PENDING,
                'portal_submission_token' => $submissionToken,
                'submitted_by_user_id' => $user->getKey(),
                'customer_reference' => filled($customerReference) ? trim($customerReference) : null,
            ]);

            foreach ($validatedLines as $index => $line) {
                $product = $products->get($line['product_id']);
                $price = $resolvedPrices[$line['product_id']];
                $lineTotal = (string) BigDecimal::of($price->amount)
                    ->multipliedBy($line['quantity'])
                    ->toScale(2, RoundingMode::HalfUp);

                $order->lines()->create([
                    'product_id' => $product->getKey(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $price->amount,
                    'currency' => $price->currency,
                    'price_source' => $price->source,
                    'total_price' => $lineTotal,
                    'description' => $product->description ?? $product->name,
                    'sku' => $product->sku,
                    'line_number' => (string) ($index + 1),
                    'unit_of_measure' => $price->unitOfMeasure !== '' ? $price->unitOfMeasure : null,
                    'external_id' => null,
                ]);
            }

            return $order->load('lines.product');
        }, 3));
    }
}
use App\Portal\Orders\NormalizeOrderQuantity;
use App\Portal\Pricing\ResolveAccountProductPrice;
