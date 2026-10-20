<?php

namespace App\Modules\DataHub\Actions;

use App\Models\Product;
use DomainException;

class UpsertProduct
{
    /**
     * @param  array{external_id: string, sku: string, name: string, description: ?string, price: string, currency: string}  $data
     */
    public function handle(string $source, array $data): bool
    {
        $product = Product::query()->firstOrNew([
            'source' => $source,
            'external_id' => $data['external_id'],
        ]);

        $skuOwner = Product::query()
            ->where('sku', $data['sku'])
            ->when($product->exists, fn ($query) => $query->where('id', '!=', $product->getKey()))
            ->exists();

        if ($skuOwner) {
            throw new DomainException("SKU [{$data['sku']}] already belongs to another product.");
        }

        $wasRecentlyCreated = ! $product->exists;
        $product->fill([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'currency' => $data['currency'],
        ])->save();

        return $wasRecentlyCreated;
    }
}
