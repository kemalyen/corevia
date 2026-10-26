<?php

namespace App\Portal\Support;

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Log;

class CatalogueAuditLogger
{
    public function accountConfigurationChanged(Account $account): void
    {
        Log::info('Vendor catalogue access configuration changed.', [
            'actor_user_id' => auth()->id(),
            'account_id' => $account->getKey(),
            'product_access_mode' => $account->product_access_mode->value,
            'product_grant_ids' => $account->permittedProducts()->orderBy('products.id')->pluck('products.id')->all(),
            'category_grant_ids' => $account->permittedProductCategories()->orderBy('product_categories.id')->pluck('product_categories.id')->all(),
        ]);
    }

    public function accountPriceChanged(string $event, AccountProductPrice $price): void
    {
        Log::info('Account-specific product price changed.', [
            'event' => $event,
            'actor_user_id' => auth()->id(),
            'account_id' => $price->account_id,
            'account_product_price_id' => $price->getKey(),
            'product_id' => $price->product_id,
            'currency' => $price->currency,
            'unit_of_measure' => $price->unit_of_measure,
            'changes' => $price->getChanges(),
        ]);
    }

    public function categoryChanged(string $event, ProductCategory $category): void
    {
        Log::info('Product category hierarchy changed.', [
            'event' => $event,
            'actor_user_id' => auth()->id(),
            'product_category_id' => $category->getKey(),
            'parent_id' => $category->parent_id,
            'product_ids' => $category->exists
                ? $category->products()->orderBy('products.id')->pluck('products.id')->all()
                : [],
            'changes' => $category->getChanges(),
        ]);
    }

    public function productCategoriesChanged(Product $product): void
    {
        Log::info('Product category assignments changed.', [
            'actor_user_id' => auth()->id(),
            'product_id' => $product->getKey(),
            'product_category_ids' => $product->categories()->orderBy('product_categories.id')->pluck('product_categories.id')->all(),
        ]);
    }
}
