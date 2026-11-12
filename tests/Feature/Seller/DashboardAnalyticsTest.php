<?php

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard never includes another merchant revenue or orders', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create(['merchant_id' => $merchant->id]);

    $ownQuote = Quote::factory()->create(['product_id' => $product->id, 'source' => 'buy_now']);
    $ownOrder = Order::factory()->paid()->create([
        'quote_id' => $ownQuote->id,
        'merchant_id' => $merchant->id,
        'amount_cents' => 30_000,
        'created_at' => now(),
    ]);

    $otherSeller = User::factory()->create();
    $otherMerchant = Merchant::factory()->create(['user_id' => $otherSeller->id]);
    $otherProduct = Product::factory()->create(['merchant_id' => $otherMerchant->id]);
    $otherQuote = Quote::factory()->create(['product_id' => $otherProduct->id, 'source' => 'buy_now']);
    Order::factory()->paid()->create([
        'quote_id' => $otherQuote->id,
        'merchant_id' => $otherMerchant->id,
        'amount_cents' => 99_000,
        'created_at' => now(),
    ]);

    $this->actingAs($seller)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/dashboard')
            ->where('stats.paid_orders', 1)
            ->where('stats.revenue', '300.00')
            ->where('stats.profit_paid_orders', 1)
            ->where('recent.0.order_number', $ownOrder->order_number));
});

test('dashboard reports verified revenue and cost-complete gross profit', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'unit_cost_cents' => 10_000,
    ]);
    $completeQuote = Quote::factory()->create(['product_id' => $product->id, 'source' => 'buy_now']);
    $completeOrder = Order::factory()->paid()->create([
        'quote_id' => $completeQuote->id,
        'merchant_id' => $merchant->id,
        'amount_cents' => 30_000,
        'created_at' => now(),
    ]);
    $completeOrder->items()->create([
        'product_id' => $product->id,
        'quote_id' => $completeQuote->id,
        'name' => $product->name,
        'sku' => $product->sku,
        'quantity' => 2,
        'unit_price_cents' => 15_000,
        'unit_cost_cents' => 10_000,
        'line_total_cents' => 30_000,
    ]);
    $unknownCostQuote = Quote::factory()->create(['product_id' => $product->id, 'source' => 'buy_now']);
    Order::factory()->paid()->create([
        'quote_id' => $unknownCostQuote->id,
        'merchant_id' => $merchant->id,
        'amount_cents' => 7_000,
    ]);
    $failedQuote = Quote::factory()->create(['product_id' => $product->id, 'source' => 'buy_now']);
    Order::factory()->create([
        'quote_id' => $failedQuote->id,
        'merchant_id' => $merchant->id,
        'amount_cents' => 80_000,
    ]);
    Quote::factory()->create([
        'product_id' => $product->id,
        'source' => 'offer',
        'status' => 'fulfilled',
    ]);
    Quote::factory()->create([
        'product_id' => $product->id,
        'source' => 'offer',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($seller)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/dashboard')
            ->where('stats.paid_orders', 2)
            ->where('stats.revenue', '370.00')
            ->where('stats.average_order_value', '185.00')
            ->where('stats.gross_profit', '100.00')
            ->where('stats.profit_costed_orders', 1)
            ->where('stats.profit_paid_orders', 2)
            ->where('stats.offer_acceptance_rate', 50)
            ->where('stats.revenue_trend.6.revenue_cents', 37_000));
});
