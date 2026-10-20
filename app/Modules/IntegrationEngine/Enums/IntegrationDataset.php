<?php

namespace App\Modules\IntegrationEngine\Enums;

enum IntegrationDataset: string
{
    case CUSTOMERS = 'customers';
    case PRODUCTS = 'products';
    case SALES_ORDERS = 'sales_orders';

    public function priority(): int
    {
        return match ($this) {
            self::CUSTOMERS => 10,
            self::PRODUCTS => 20,
            self::SALES_ORDERS => 30,
        };
    }
}
