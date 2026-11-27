<?php

namespace App\Modules\DataHub\Actions;

use App\Models\Product;
use App\Models\ProductCategory;
use DomainException;

class MapProductCategory
{
    /**
     * @param  array{product_external_id: string, category_name: string}  $data
     */
    public function handle(array $data): bool
    {
        $product = Product::query()->where('external_id', $data['product_external_id'])->first();

        if ($product === null) {
            throw new DomainException("Unknown product [{$data['product_external_id']}].");
        }

        $categories = ProductCategory::query()->where('name', $data['category_name'])->get();

        if ($categories->isEmpty()) {
            throw new DomainException("Unknown product category [{$data['category_name']}].");
        }

        if ($categories->count() > 1) {
            throw new DomainException("Product category [{$data['category_name']}] is ambiguous.");
        }

        $category = $categories->firstOrFail();
        $alreadyMapped = $product->categories()->whereKey($category->getKey())->exists();

        if (! $alreadyMapped) {
            $product->categories()->attach($category);
        }

        return ! $alreadyMapped;
    }
}
