<?php

namespace App\Modules\IntegrationEngine\Enums;

enum IntegrationDataset: string
{
    case ACCOUNTS = 'accounts';
    case PRODUCTS = 'products';
    case ACCOUNT_PRODUCT_PRICES = 'account_product_prices';
    case PRODUCT_CATEGORY_MAPPINGS = 'product_category_mappings';
    case SALES_ORDERS = 'sales_orders';

    public function priority(): int
    {
        return match ($this) {
            self::ACCOUNTS => 10,
            self::PRODUCTS => 20,
            self::ACCOUNT_PRODUCT_PRICES => 30,
            self::PRODUCT_CATEGORY_MAPPINGS => 40,
            self::SALES_ORDERS => 50,
        };
    }
}
