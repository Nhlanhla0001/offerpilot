<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => null,
            'target_price_cents' => 20_000,
            'ceiling_price_cents' => 22_500,
            'agreed_price_cents' => 22_000,
            'rounds_used' => 1,
            'status' => QuoteStatus::Pending,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    /**
     * Indicate that the quote has already expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
