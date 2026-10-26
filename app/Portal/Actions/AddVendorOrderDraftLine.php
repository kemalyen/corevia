<?php

namespace App\Portal\Actions;

use App\Models\Product;
use App\Models\User;
use App\Portal\Queries\VendorCatalogueQuery;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

class AddVendorOrderDraftLine
{
    public function __construct(private VendorCatalogueQuery $catalogueQuery) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user, int $productId, string|int|float $quantity, array $lines): array
    {
        $normalizedQuantity = (string) $quantity;

        if (preg_match('/^\d{1,6}(?:\.\d{1,4})?$/', $normalizedQuantity) !== 1
            || BigDecimal::of($normalizedQuantity)->isLessThanOrEqualTo(0)) {
            throw new DomainException('The quantity must be greater than zero with no more than four decimal places.');
        }

        $product = $this->catalogueQuery->findOrderableForUser($user, $productId);

        if (! $product instanceof Product || $product->currency === null) {
            throw new DomainException('This product is not available with valid pricing.');
        }

        if (BigDecimal::of((string) $product->price)->isNegative()) {
            throw new DomainException('This product is not available with valid pricing.');
        }

        $otherCurrency = collect($lines)
            ->pluck('currency')
            ->first(fn (string $currency): bool => $currency !== $product->currency);

        if ($otherCurrency !== null) {
            throw new DomainException('All lines in one order must use the same currency.');
        }

        $price = (string) $product->price;
        $line = [
            'product_id' => $product->getKey(),
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $price,
            'currency' => $product->currency,
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
