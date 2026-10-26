<?php

namespace App\Portal\Actions;

use App\Enums\ErpSyncState;
use App\Enums\OrderOrigin;
use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
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

            $normalizedQuantity = (string) $quantity;

            if (preg_match('/^\d{1,6}(?:\.\d{1,4})?$/', $normalizedQuantity) !== 1
                || BigDecimal::of($normalizedQuantity)->isLessThanOrEqualTo(0)) {
                throw new DomainException('Every order line must contain a valid product and positive quantity.');
            }

            $validatedLines[] = [
                'product_id' => $line['product_id'],
                'quantity' => $normalizedQuantity,
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
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw new DomainException('One or more selected products are not available to this account.');
            }

            $currencies = $products->pluck('currency')->filter()->unique();

            if ($products->contains(fn (Product $product): bool => $product->currency === null)) {
                throw new DomainException('A selected product does not have a currency and cannot be ordered.');
            }

            if ($products->contains(fn (Product $product): bool => BigDecimal::of((string) $product->price)->isNegative())) {
                throw new DomainException('A selected product has an invalid price and cannot be ordered.');
            }

            if ($currencies->count() !== 1) {
                throw new DomainException('All products in an order must use the same currency.');
            }

            $totalAmount = '0';

            foreach ($validatedLines as $line) {
                $product = $products->get($line['product_id']);
                $lineTotal = BigDecimal::of((string) $product->price)
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
                $lineTotal = (string) BigDecimal::of((string) $product->price)
                    ->multipliedBy($line['quantity'])
                    ->toScale(2, RoundingMode::HalfUp);

                $order->lines()->create([
                    'product_id' => $product->getKey(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $lineTotal,
                    'description' => $product->description ?? $product->name,
                    'sku' => $product->sku,
                    'line_number' => (string) ($index + 1),
                    'unit_of_measure' => null,
                    'external_id' => null,
                ]);
            }

            return $order->load('lines.product');
        }, 3));
    }
}
