<?php

namespace App\Modules\IntegrationEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedSalesOrderRow extends Model
{
    protected $attributes = ['currency' => 'GBP'];

    protected $fillable = [
        'row_number', 'order_external_id', 'order_number', 'order_date', 'account_external_id',
        'status', 'currency', 'total_amount', 'line_external_id', 'line_number',
        'product_external_id', 'quantity', 'unit_price', 'line_total', 'description', 'sku',
        'unit_of_measure',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'immutable_date',
            'total_amount' => 'decimal:2',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(Ingestion::class);
    }
}
