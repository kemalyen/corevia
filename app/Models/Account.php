<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'account_type',
        'account_status',
    ];

    protected $casts = [
        'account_status' => \App\Enums\AccountStatus::class,
        'account_type' => \App\Enums\AccountType::class,
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function sales_orders()
    {
        return $this->hasMany(SalesOrder::class);
    }
}
