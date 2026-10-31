<?php

namespace App\Portal\Pricing;

use App\Enums\PriceSource;
use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use Brick\Math\BigDecimal;
use DomainException;
use Illuminate\Support\Str;

class ResolveAccountProductPrice
{
    public function resolve(
        Account $account,
        Product $product,
        ?string $currency = null,
        ?string $unitOfMeasure = null,
    ): ?EffectivePrice {
        $requestedCurrency = $this->normalizeCurrency($currency ?? $product->currency);
        $requestedUnit = $this->normalizeUnit($unitOfMeasure ?? $product->unit_of_measure);

        if ($requestedCurrency === null) {
            return null;
        }

        $accountPrice = $product->relationLoaded('accountPrices')
            ? $product->accountPrices->first(fn (AccountProductPrice $price): bool => $price->account_id === $account->getKey()
                && $price->currency === $requestedCurrency
                && $price->unit_of_measure === $requestedUnit)
            : AccountProductPrice::query()
                ->whereBelongsTo($account)
                ->whereBelongsTo($product)
                ->where('currency', $requestedCurrency)
                ->where('unit_of_measure', $requestedUnit)
                ->first(['amount', 'currency', 'unit_of_measure']);

        if ($accountPrice !== null) {
            return new EffectivePrice(
                $this->validatedAmount($accountPrice->amount),
                $accountPrice->currency,
                $accountPrice->unit_of_measure,
                PriceSource::ACCOUNT_SPECIFIC,
            );
        }

        if ($product->price === null
            || $this->normalizeCurrency($product->currency) !== $requestedCurrency
            || $this->normalizeUnit($product->unit_of_measure) !== $requestedUnit) {
            return null;
        }

        return new EffectivePrice(
            $this->validatedAmount($product->price),
            $requestedCurrency,
            $requestedUnit,
            PriceSource::PRODUCT_FEED,
        );
    }

    private function validatedAmount(string $amount): string
    {
        $decimal = BigDecimal::of($amount);

        if ($decimal->isNegative()) {
            throw new DomainException('Negative product prices are not permitted.');
        }

        return (string) $decimal;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null || trim($currency) === '') {
            return null;
        }

        return Str::upper(trim($currency));
    }

    private function normalizeUnit(?string $unitOfMeasure): string
    {
        return Str::upper(trim((string) $unitOfMeasure));
    }
}
