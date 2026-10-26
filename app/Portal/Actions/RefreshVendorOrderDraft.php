<?php

namespace App\Portal\Actions;

use App\Models\Product;
use App\Models\User;
use App\Portal\Orders\NormalizeOrderQuantity;
use App\Portal\Queries\VendorCatalogueQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class RefreshVendorOrderDraft
{
    public function __construct(
        private VendorCatalogueQuery $catalogueQuery,
        private NormalizeOrderQuantity $normalizeQuantity,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user, array $lines): array
    {
        if ($lines === [] || count($lines) > 100) {
            throw new DomainException('Add at least one product before submitting the order.');
        }

        $normalizedLines = [];

        foreach ($lines as $line) {
            $quantity = $line['quantity'] ?? null;

            if (! isset($line['product_id']) || ! is_numeric($line['product_id'])
                || (! is_string($quantity) && ! is_int($quantity) && ! is_float($quantity))) {
                throw new DomainException('Every order line must contain a valid product and positive quantity.');
            }

            $normalizedLines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => $this->normalizeQuantity->handle($quantity),
            ];
        }

        $productIds = collect($normalizedLines)->pluck('product_id');

        if ($productIds->duplicates()->isNotEmpty()) {
            throw new DomainException('Each product may appear only once in an order.');
        }

        $products = $this->catalogueQuery->forUser($user)
            ->whereKey($productIds)
            ->get(['id', 'sku', 'name', 'description', 'price', 'currency', 'unit_of_measure'])
            ->keyBy('id');

        if ($products->count() !== $productIds->unique()->count()) {
            throw new DomainException('One or more selected products are not available to this account.');
        }

        $draft = [];

        foreach ($normalizedLines as $line) {
            /** @var Product $product */
            $product = $products->get($line['product_id']);
            $price = $this->catalogueQuery->effectivePriceForUser($user, $product);

            if ($price === null) {
                throw new DomainException("Pricing is unavailable for product [{$product->sku}].");
            }

            $draft[] = [
                'product_id' => $product->getKey(),
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => $price->amount,
                'currency' => $price->currency,
                'unit_of_measure' => $price->unitOfMeasure,
                'price_source' => $price->source->value,
                'quantity' => $line['quantity'],
                'total' => (string) BigDecimal::of($price->amount)
                    ->multipliedBy($line['quantity'])
                    ->toScale(2, RoundingMode::HalfUp),
            ];
        }

        if (collect($draft)->pluck('currency')->unique()->count() !== 1) {
            throw new DomainException('All lines in one order must use the same currency.');
        }

        return $draft;
    }
}
