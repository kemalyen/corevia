<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Currency: string implements HasLabel
{
    case GBP = 'GBP';
    case USD = 'USD';
    case EUR = 'EUR';
    case JPY = 'JPY';
    case CNY = 'CNY';
    case AUD = 'AUD';
    case CAD = 'CAD';
    case CHF = 'CHF';
    case HKD = 'HKD';
    case SGD = 'SGD';
    case INR = 'INR';
    case KRW = 'KRW';
    case MXN = 'MXN';
    case BRL = 'BRL';
    case RUB = 'RUB';
    case ZAR = 'ZAR';
    case SEK = 'SEK';
    case NOK = 'NOK';
    case NZD = 'NZD';
    case AED = 'AED';

    public function getLabel(): string
    {
        return match ($this) {
            self::GBP => 'GBP — British Pound',
            self::USD => 'USD — US Dollar',
            self::EUR => 'EUR — Euro',
            self::JPY => 'JPY — Japanese Yen',
            self::CNY => 'CNY — Chinese Yuan',
            self::AUD => 'AUD — Australian Dollar',
            self::CAD => 'CAD — Canadian Dollar',
            self::CHF => 'CHF — Swiss Franc',
            self::HKD => 'HKD — Hong Kong Dollar',
            self::SGD => 'SGD — Singapore Dollar',
            self::INR => 'INR — Indian Rupee',
            self::KRW => 'KRW — South Korean Won',
            self::MXN => 'MXN — Mexican Peso',
            self::BRL => 'BRL — Brazilian Real',
            self::RUB => 'RUB — Russian Ruble',
            self::ZAR => 'ZAR — South African Rand',
            self::SEK => 'SEK — Swedish Krona',
            self::NOK => 'NOK — Norwegian Krone',
            self::NZD => 'NZD — New Zealand Dollar',
            self::AED => 'AED — UAE Dirham',
        };
    }
}
