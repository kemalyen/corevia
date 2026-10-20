<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AccountType: string  implements HasLabel, HasColor
{
    case VENDOR = 'vendor';
    case SUPPLIER = 'supplier';

    public function getLabel(): string | Htmlable | null
    {
        return match ($this) {
            self::VENDOR => 'Vendor',
            self::SUPPLIER => 'Supplier',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::VENDOR => 'primary',
            self::SUPPLIER => 'success',
        };
    }
}
