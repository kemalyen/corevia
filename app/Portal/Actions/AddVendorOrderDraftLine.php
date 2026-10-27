<?php

namespace App\Portal\Actions;

use App\Models\Product;
use App\Models\User;
use App\Portal\Orders\NormalizeOrderQuantity;
use App\Portal\Queries\VendorCatalogueQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class AddVendorOrderDraftLine
{
    public function __construct(
        private VendorCatalogueQuery $catalogueQuery,
        private NormalizeOrderQuantity $normalizeQuantity,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user, int $productId, string|int|float $quantity, array $lines): array
    {
        $normalizedQuantity = $this->normalizeQuantity->handle($quantity);

        $product = $this->catalogueQuery->findOrderableForUser($user, $productId);

        if (! $product instanceof Product) {
            throw new DomainException('This product is not available with valid pricing.');
        }

        $effectivePrice = $this->catalogueQuery->effectivePriceForUser($user, $product);

        if ($effectivePrice === null) {
            throw new DomainException('Pricing is unavailable for this product, currency, and unit.');
        }

        $otherCurrency = collect($lines)->first(fn (array $line): bool => ! isset($line['currency'])
            || $line['currency'] !== $effectivePrice->currency);

        if ($otherCurrency !== null) {
            throw new DomainException('All lines in one order must use the same currency.');
        }

        $price = $effectivePrice->amount;
        $line = [
            'product_id' => $product->getKey(),
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $price,
            'currency' => $effectivePrice->currency,
            'unit_of_measure' => $effectivePrice->unitOfMeasure,
            'price_source' => $effectivePrice->source->value,
            'quantity' => $normalizedQuantity,
            'total' => (string) BigDecimal::of($price)
                ->multipliedBy($normalizedQuantity)
                ->toScale(2, RoundingMode::HalfUp),
        ];
        $existingIndex = collect($lines)
            ->search(fn (array $candidate): bool => $candidate['product_id'] === $product->getKey());

        if ($existingIndex === false) {
            $lines[] = $line;
        } else {
            $lines[$existingIndex] = $line;
        }

        return array_values($lines);
    }
}
