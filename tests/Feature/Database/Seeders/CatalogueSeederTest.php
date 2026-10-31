<?php

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\AccountCatalogueSeeder;
use Database\Seeders\ProductSeeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;

it('seeds product categories and account-specific catalogue assignments', function (): void {
    $accounts = Account::factory()->count(3)->create();

    seed([
        ProductSeeder::class,
        AccountCatalogueSeeder::class,
    ]);

    assertDatabaseCount('products', 10);
    assertDatabaseCount('product_categories', 5);

    Product::query()->with('categories')->each(function (Product $product): void {
        expect($product->categories)->not->toBeEmpty();
    });

    ProductCategory::query()->withCount('products')->each(function (ProductCategory $category): void {
        expect($category->products_count)->toBeGreaterThan(0);
    });

    $accounts->each(function (Account $account): void {
        $account->load(['permittedProductCategories', 'permittedProducts', 'accountPrices.product']);

        expect($account->permittedProductCategories)->not->toBeEmpty()
            ->and($account->permittedProducts)->not->toBeEmpty()
            ->and($account->accountPrices)->not->toBeEmpty();

        $account->accountPrices->each(function (AccountProductPrice $price) use ($account): void {
            expect($account->permittedProducts->modelKeys())->toContain($price->product_id)
                ->and($price->currency)->toBe($price->product->currency)
                ->and($price->unit_of_measure)->toBe($price->product->unit_of_measure ?? '');
        });
    });
});
