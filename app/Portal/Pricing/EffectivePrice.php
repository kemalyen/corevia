<?php

namespace App\Portal\Pricing;

use App\Enums\PriceSource;

final readonly class EffectivePrice
{
    public function __construct(
        public string $amount,
        public string $currency,
        public string $unitOfMeasure,
        public PriceSource $source,
    ) {}

    /** @return array{amount: string, currency: string, unit_of_measure: string, source: string} */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'unit_of_measure' => $this->unitOfMeasure,
            'source' => $this->source->value,
        ];
    }
}
