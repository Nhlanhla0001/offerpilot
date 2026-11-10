<?php

namespace Database\Factories;

use App\Models\MerchantRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantRule>
 */
class MerchantRuleFactory extends Factory
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
            'floor_price_cents' => 21_000,
            'max_rounds' => 3,
            'allow_alternatives' => true,
        ];
    }

    /**
     * Indicate that the merchant will not offer alternative products.
     */
    public function withoutAlternatives(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_alternatives' => false,
        ]);
    }
}
