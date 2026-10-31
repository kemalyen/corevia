<?php

namespace App\Models;

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'account_number',
        'name',
        'account_type',
        'account_status',
        'product_access_mode',
        'external_id',
    ];

    protected $attributes = [
        'product_access_mode' => AccountProductAccessMode::RESTRICTED->value,
    ];

    protected $casts = [
        'account_status' => AccountStatus::class,
        'account_type' => AccountType::class,
        'product_access_mode' => AccountProductAccessMode::class,
    ];

    public function isVendor(): bool
    {
        return $this->account_type === AccountType::VENDOR;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<SalesOrder, $this> */
    public function sales_orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function permittedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'account_product_grants')->withTimestamps();
    }

    /** @return BelongsToMany<ProductCategory, $this> */
    public function permittedProductCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'account_product_category_grants')->withTimestamps();
    }

    /** @return HasMany<AccountProductPrice, $this> */
    public function accountPrices(): HasMany
    {
        return $this->hasMany(AccountProductPrice::class);
    }

    /** @return HasMany<AssistantConversation, $this> */
    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }
}
