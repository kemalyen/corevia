<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceSource: string implements HasLabel
{
    case ACCOUNT_SPECIFIC = 'account_specific';
    case PRODUCT_FEED = 'product_feed';
    case ERP_CONFIRMED = 'erp_confirmed';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACCOUNT_SPECIFIC => 'Account-specific price',
            self::PRODUCT_FEED => 'Product feed price',
            self::ERP_CONFIRMED => 'ERP-confirmed price',
        };
    }
}
