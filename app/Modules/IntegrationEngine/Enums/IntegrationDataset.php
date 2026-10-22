<?php

namespace App\Modules\IntegrationEngine\Enums;

enum IntegrationDataset: string
{
    case ACCOUNTS = 'accounts';
    case PRODUCTS = 'products';
    case SALES_ORDERS = 'sales_orders';

    public function priority(): int
    {
        return match ($this) {
            self::ACCOUNTS => 10,
            self::PRODUCTS => 20,
            self::SALES_ORDERS => 30,
        };
    }
}
