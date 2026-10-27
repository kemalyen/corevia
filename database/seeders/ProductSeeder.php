<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::factory()->count(10)->create();
        $categories = ProductCategory::factory()->count(5)->create();

        $products->each(function (Product $product) use ($categories): void {
            $categoryCount = random_int(1, max(1, min(3, $categories->count())));

            $product->categories()->sync(
                $categories->shuffle()->take($categoryCount)->modelKeys(),
            );
        });

        $categories->each(function (ProductCategory $category) use ($products): void {
            if (! $category->products()->exists()) {
                $category->products()->attach($products->random());
            }
        });
    }
}
