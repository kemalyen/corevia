<?php

namespace App\Portal\Orders;

use Brick\Math\BigDecimal;
use DomainException;

class NormalizeOrderQuantity
{
    public function handle(string|int|float $quantity): string
    {
        $normalized = (string) $quantity;

        if (preg_match('/^\d{1,6}(?:\.\d{1,4})?$/', $normalized) !== 1
            || BigDecimal::of($normalized)->isLessThanOrEqualTo(0)) {
            throw new DomainException('The quantity must be greater than zero with no more than four decimal places.');
        }

        return $normalized;
    }
}
