<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderLine>
 */
class SalesOrderLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productIds = Product::pluck('id')->toArray();
        return [
            'product_id' => $this->faker->randomElement($productIds),
            'quantity' => $this->faker->numberBetween(1, 10),
            'unit_price' => $this->faker->randomFloat(2, 1, 100),
            'total_price' => function (array $attributes) {
                return $attributes['quantity'] * $attributes['unit_price'];
            },
            'description' => $this->faker->sentence(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'line_number' => $this->faker->unique()->numberBetween(1, 100),
            'unit_of_measure' => $this->faker->randomElement(['pcs', 'kg', 'liters', 'boxes']),

        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (SalesOrderLine $salesOrderLine) {
            // Update the total_amount of the associated SalesOrder
            $salesOrder = $salesOrderLine->salesOrder;
            $salesOrder->total_amount += $salesOrderLine->total_price;
            $salesOrder->save();
        });
    }
}
