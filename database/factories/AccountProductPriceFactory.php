<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountProductPrice>
 */
class AccountProductPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'product_id' => Product::factory(),
            'amount' => fake()->randomFloat(4, 0, 1000),
            'currency' => 'GBP',
            'unit_of_measure' => 'EA',
        ];
    }
}
