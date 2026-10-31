<?php

namespace App\Portal\Catalogue;

use App\Enums\AccountProductAccessMode;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class AccountProductVisibility
{
    /** @return Builder<Product> */
    public function query(Account $account): Builder
    {
        $query = Product::query();

        if ($account->product_access_mode === AccountProductAccessMode::ALL) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($account): void {
            $visible->whereExists(function ($grant) use ($account): void {
                $grant->selectRaw('1')
                    ->from('account_product_grants')
                    ->whereColumn('account_product_grants.product_id', 'products.id')
                    ->where('account_product_grants.account_id', $account->getKey());
            })->orWhereRaw(
                <<<'SQL'
products.id IN (
    WITH RECURSIVE visible_categories(id) AS (
        SELECT product_category_id
        FROM account_product_category_grants
        WHERE account_id = ?
        UNION
        SELECT product_categories.id
        FROM product_categories
        INNER JOIN visible_categories ON product_categories.parent_id = visible_categories.id
    )
    SELECT product_category_product.product_id
    FROM product_category_product
    INNER JOIN visible_categories ON visible_categories.id = product_category_product.product_category_id
)
SQL,
                [$account->getKey()],
            );
        });
    }

    public function find(Account $account, int $productId): ?Product
    {
        return $this->query($account)->whereKey($productId)->first();
    }
}
