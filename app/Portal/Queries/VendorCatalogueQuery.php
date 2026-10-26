<?php

namespace App\Portal\Queries;

use App\Models\Product;
use App\Models\User;
use App\Portal\VendorAccountContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class VendorCatalogueQuery
{
    public function __construct(private VendorAccountContext $accountContext) {}

    /** @return Builder<Product> */
    public function forUser(User $user): Builder
    {
        $this->accountContext->resolve($user);

        return Product::query()->orderBy('sku');
    }

    /**
     * @return array{results: list<array{id: int, code: string, name: string, label: string, availability: null}>, has_more: bool}
     */
    public function search(User $user, string $search, int $limit = 20): array
    {
        $search = Str::lower(trim($search));

        if (Str::length($search) < 2) {
            return ['results' => [], 'has_more' => false];
        }

        $limit = max(1, min($limit, 20));
        $prefix = $this->escapeLike($search).'%';
        $products = $this->forUser($user)
            ->reorder()
            ->where(function (Builder $query) use ($prefix): void {
                $query->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$prefix])
                    ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$prefix]);
            })
            ->orderByRaw('CASE WHEN LOWER(sku) = ? THEN 0 WHEN LOWER(sku) LIKE ? ESCAPE \'!\' THEN 1 ELSE 2 END', [$search, $prefix])
            ->orderBy('sku')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['id', 'sku', 'name']);
        $results = [];

        foreach ($products->take($limit) as $product) {
            $results[] = [
                'id' => (int) $product->getKey(),
                'code' => $product->sku,
                'name' => $product->name,
                'label' => "{$product->sku} — {$product->name}",
                'availability' => null,
            ];
        }

        return [
            'results' => $results,
            'has_more' => $products->count() > $limit,
        ];
    }

    public function findOrderableForUser(User $user, int $productId): ?Product
    {
        return $this->forUser($user)
            ->whereKey($productId)
            ->first(['id', 'sku', 'name', 'description', 'price', 'currency']);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
