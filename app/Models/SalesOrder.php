<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'account_id',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'status' => \App\Enums\OrderStatus::class,
    ];

    public function lines()
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
