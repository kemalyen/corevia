<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $price
 * @property string $sku
 * @property string|null $currency
 * @property string|null $unit_of_measure
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $attributes = ['currency' => 'GBP'];

    protected $fillable = [
        'name',
        'description',
        'price',
        'sku',
        'currency',
        'unit_of_measure',
        'external_id',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:4'];
    }

    /** @return BelongsToMany<ProductCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_category_product')->withTimestamps();
    }

    /** @return BelongsToMany<Account, $this> */
    public function permittedAccounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_product_grants')->withTimestamps();
    }

    /** @return HasMany<AccountProductPrice, $this> */
    public function accountPrices(): HasMany
    {
        return $this->hasMany(AccountProductPrice::class);
    }
}
