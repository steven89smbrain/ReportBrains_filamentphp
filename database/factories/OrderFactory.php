<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
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
            'invoice_no' => 'INV-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'branch_id' => Branch::factory(),
            'status' => fake()->randomElement(['paid', 'pending', 'cancelled']),
            'total' => fake()->randomFloat(2, 100_000, 25_000_000),
            'ordered_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }

    /**
     * Indicate that the order has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }
}
