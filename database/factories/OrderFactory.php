<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Quote;
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
            'quote_id' => Quote::factory(),
            'paypal_order_id' => fake()->unique()->bothify('PAYPAL-##########'),
            'paypal_capture_id' => null,
            'amount_cents' => 22_000,
            'currency' => 'USD',
            'status' => OrderStatus::Created,
        ];
    }

    /**
     * Indicate that the order has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paypal_capture_id' => fake()->unique()->bothify('CAPTURE-##########'),
        ]);
    }
}
