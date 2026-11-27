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
        $products = Product::factory()->count(200)->create();
        $categories = ProductCategory::bulkInsert($this->getSampleCategories());

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

    private function getSampleCategories(): array
    {
        return [

            ['name' => 'Fashion & Apparel / Women\'s Clothing / Tops & T-Shirts'],
            ['name' => 'Fashion & Apparel / Women\'s Clothing / Dresses & Jumpsuits'],
            ['name' => 'Fashion & Apparel / Women\'s Clothing / Outerwear & Jackets'],
            ['name' => 'Fashion & Apparel / Men\'s Clothing / Shirts & Polos'],
            ['name' => 'Fashion & Apparel / Men\'s Clothing / Trousers & Jeans'],
            ['name' => 'Fashion & Apparel / Men\'s Clothing / Suits & Blazers'],
            ['name' => 'Fashion & Apparel / Shoes / Sneakers'],
            ['name' => 'Fashion & Apparel / Shoes / Boots'],
            ['name' => 'Fashion & Apparel / Shoes / Formal Shoes'],
            ['name' => 'Fashion & Apparel / Accessories / Bags & Backpacks'],
            ['name' => 'Fashion & Apparel / Accessories / Belts & Wallets'],
            ['name' => 'Fashion & Apparel / Accessories / Jewelry & Watches'],
            ['name' => 'Electronics & Gadgets / Computers & Laptops / MacBooks & Ultrabooks'],
            ['name' => 'Electronics & Gadgets / Computers & Laptops / Gaming Laptops'],
            ['name' => 'Electronics & Gadgets / Computers & Laptops / Desktop PCs & Monitors'],
            ['name' => 'Electronics & Gadgets / Phones & Tablets / Smartphones'],
            ['name' => 'Electronics & Gadgets / Phones & Tablets / Smartwatches & Wearables'],
            ['name' => 'Electronics & Gadgets / Phones & Tablets / Tablet Cases & Chargers'],
            ['name' => 'Electronics & Gadgets / Audio & Video / Wireless Headphones'],
            ['name' => 'Electronics & Gadgets / Audio & Video / Bluetooth Speakers'],
            ['name' => 'Electronics & Gadgets / Audio & Video / TVs & Streaming Devices'],
            ['name' => 'Home, Living & Garden / Furniture / Sofas & Seating'],
            ['name' => 'Home, Living & Garden / Furniture / Tables & Desks'],
            ['name' => 'Home, Living & Garden / Furniture / Storage & Shelving'],
            ['name' => 'Home, Living & Garden / Bed & Bath / Bedding & Sheets'],
            ['name' => 'Home, Living & Garden / Bed & Bath / Towels & Bath Mats'],
            ['name' => 'Home, Living & Garden / Bed & Bath / Pillows & Mattresses'],
            ['name' => 'Home, Living & Garden / Kitchen & Dining / Cookware & Utensils'],
            ['name' => 'Home, Living & Garden / Kitchen & Dining / Small Appliances'],
            ['name' => 'Home, Living & Garden / Kitchen & Dining / Dinnerware & Glassware'],
            ['name' => 'Health & Beauty / Skincare / Cleansers & Serums'],
            ['name' => 'Health & Beauty / Skincare / Moisturizers & Sunscreen'],
            ['name' => 'Health & Beauty / Haircare / Shampoos & Conditioners'],
            ['name' => 'Health & Beauty / Haircare / Styling Tools'],
            ['name' => 'Health & Beauty / Vitamins & Supplements / Daily Multivitamins'],
            ['name' => 'Health & Beauty / Vitamins & Supplements / Protein & Fitness Nutrition'],
        ];
    }
}
