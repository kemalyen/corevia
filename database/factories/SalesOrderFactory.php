<?php

namespace Database\Factories;

use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountIds = \App\Models\Account::pluck('id')->toArray();
        return [
            'order_number' => $this->faker->unique()->bothify('SO-####'),
            'order_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'account_id' => $this->faker->randomElement($accountIds),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'status' => $this->faker->randomElement(\App\Enums\OrderStatus::cases())->value,
        ];
    }
}
