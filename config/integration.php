<?php

$datasets = [
    'accounts' => [
        'prefix' => 'accounts-',
        'headers' => [
            'account_number' => 'account_number',
            'account_external_id' => 'external_id',
            'account_type' => 'account_type',
            'name' => 'name',
            'status' => 'status',
        ],
    ],
    'products' => [
        'prefix' => 'products-',
        'headers' => [
            'product_external_id' => 'external_id',
            'sku' => 'sku',
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'currency' => 'currency',
        ],
    ],
    'account_product_prices' => [
        'prefix' => 'account-product-prices-',
        'headers' => [
            'account_external_id' => 'account_external_id',
            'product_external_id' => 'product_external_id',
            'amount' => 'amount',
            'currency' => 'currency',
            'unit_of_measure' => 'unit_of_measure',
        ],
    ],
    'product_category_mappings' => [
        'prefix' => 'product-category-mappings-',
        'headers' => [
            'product_external_id' => 'product_external_id',
            'category_name' => 'category_name',
        ],
    ],
    'sales_orders' => [
        'prefix' => 'sales-orders-',
        'headers' => [
            'order_external_id' => 'order_external_id',
            'order_number' => 'order_number',
            'order_date' => 'order_date',
            'account_external_id' => 'account_external_id',
            'status' => 'status',
            'currency' => 'currency',
            'total_amount' => 'total_amount',
            'line_external_id' => 'line_external_id',
            'line_number' => 'line_number',
            'product_external_id' => 'product_external_id',
            'quantity' => 'quantity',
            'unit_price' => 'unit_price',
            'line_total' => 'line_total',
            'description' => 'description',
            'sku' => 'sku',
            'unit_of_measure' => 'unit_of_measure',
        ],
    ],
];

return [
    'root' => env('INTEGRATION_ROOT', storage_path('app/private/integration')),
    'ready_suffix' => '.ready.csv',
    'directory_mode' => 0750,
    'file_mode' => 0640,
    'queue' => env('INTEGRATION_QUEUE', 'integrations'),
    'max_file_bytes' => (int) env('INTEGRATION_MAX_FILE_BYTES', 50 * 1024 * 1024),
    'max_rows' => (int) env('INTEGRATION_MAX_ROWS', 100_000),
    'stale_after_minutes' => (int) env('INTEGRATION_STALE_AFTER_MINUTES', 30),
    'delimiter' => ',',
    'encoding' => 'UTF-8',
    'date_format' => 'Y-m-d',
    'datasets' => $datasets,
];
