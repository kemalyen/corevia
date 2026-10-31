<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use LogicException;

class AccountCatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = Account::query()->get();
        $products = Product::query()->get();
        $categories = ProductCategory::query()->get();

        if ($accounts->isEmpty()) {
            return;
        }

        if ($products->isEmpty() || $categories->isEmpty()) {
            throw new LogicException('Products and product categories must be seeded before account catalogue access.');
        }

        $accounts->each(function (Account $account) use ($categories, $products): void {
            $categoryCount = random_int(1, max(1, min(3, $categories->count())));
            $productCount = random_int(1, max(1, min(5, $products->count())));
            $permittedProducts = $products->shuffle()->take($productCount);

            $account->permittedProductCategories()->sync(
                $categories->shuffle()->take($categoryCount)->modelKeys(),
            );
            $account->permittedProducts()->sync($permittedProducts->modelKeys());
            $account->accountPrices()->delete();

            $priceCount = random_int(1, max(1, min(3, $permittedProducts->count())));

            $permittedProducts->shuffle()->take($priceCount)->each(
                function (Product $product) use ($account): void {
                    AccountProductPrice::factory()
                        ->for($account)
                        ->for($product)
                        ->create([
                            'currency' => $product->currency,
                            'unit_of_measure' => $product->unit_of_measure ?? '',
                        ]);
                },
            );
        });
    }
}
