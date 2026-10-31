<?php

namespace App\Models;

use App\Portal\Support\CatalogueAuditLogger;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'parent_id'];

    protected static function booted(): void
    {
        static::saving(function (ProductCategory $category): void {
            $ancestorId = $category->parent_id;
            $visited = [];

            while ($ancestorId !== null) {
                if ($ancestorId === $category->getKey() || in_array($ancestorId, $visited, true)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'A product category cannot be its own ancestor.',
                    ]);
                }

                $visited[] = $ancestorId;
                $ancestorId = self::query()->whereKey($ancestorId)->value('parent_id');
            }
        });
        static::saved(fn (ProductCategory $category) => app(CatalogueAuditLogger::class)->categoryChanged('saved', $category));
        static::deleted(fn (ProductCategory $category) => app(CatalogueAuditLogger::class)->categoryChanged('deleted', $category));
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ProductCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_product')->withTimestamps();
    }

    /** @return BelongsToMany<Account, $this> */
    public function permittedAccounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_product_category_grants')->withTimestamps();
    }
}
