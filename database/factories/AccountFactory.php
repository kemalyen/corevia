<?php

namespace Database\Factories;

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'account_number' => $this->faker->unique()->bothify('ACC-####'),
            'account_type' => $this->faker->randomElement(AccountType::cases()),
            'account_status' => $this->faker->randomElement(AccountStatus::cases()),
            'product_access_mode' => AccountProductAccessMode::RESTRICTED,
        ];
    }

    public function withAllProducts(): static
    {
        return $this->state(fn (): array => [
            'product_access_mode' => AccountProductAccessMode::ALL,
        ]);
    }
}
