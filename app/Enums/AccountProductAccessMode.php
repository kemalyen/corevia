<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AccountProductAccessMode: string implements HasLabel
{
    case ALL = 'all';
    case RESTRICTED = 'restricted';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALL => 'All products',
            self::RESTRICTED => 'Restricted products',
        };
    }
}
