<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'account_id',
        'total_amount',
        'status',
        'currency',
        'external_id',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
