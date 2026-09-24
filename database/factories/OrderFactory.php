<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-' . now()->format('YmdHis') . '-' . strtoupper($this->faker->lexify('??????')),
            'product_id'   => \App\Models\Product::factory(),
            'quantity'     => $this->faker->numberBetween(1, 5),
            'checkout_price' => $this->faker->numberBetween(100000, 2000000),
            'total'        => $this->faker->numberBetween(100000, 10000000),
            'status'       => 'pending',
        ];
    }
}