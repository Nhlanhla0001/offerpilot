<?php

namespace Database\Factories;

use App\Enums\PaymentAction;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'action' => PaymentAction::Create,
            'status' => 'COMPLETED',
            'payload' => ['id' => 'PAYPAL-0000000000', 'status' => 'COMPLETED'],
            'error_message' => null,
        ];
    }
}
