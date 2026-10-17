<?php

namespace Database\Factories;

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
            'account_type' => $this->faker->randomElement(\App\Enums\AccountType::cases()),
            'account_status' => $this->faker->randomElement(\App\Enums\AccountStatus::cases()),
        ];
    }
}
