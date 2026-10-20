<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'account_type',
        'account_status',
        'source',
        'external_id',
    ];

    protected $casts = [
        'account_status' => AccountStatus::class,
        'account_type' => AccountType::class,
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
