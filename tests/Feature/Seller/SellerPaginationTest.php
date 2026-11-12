<?php

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;

test('inventory pagination retains search and stays within the seller shop', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $otherMerchant = Merchant::factory()->create();
    Product::factory()->count(12)->create([
        'merchant_id' => $merchant->id,
        'category' => 'Audio',
    ]);
    Product::factory()->create([
        'merchant_id' => $otherMerchant->id,
        'category' => 'Audio',
    ]);

    $this->actingAs($seller)
        ->get('/inventory?q=Audio&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/index')
            ->has('products.data', 2)
            ->where('products.current_page', 2)
            ->where('products.total', 12)
            ->where('filters.q', 'Audio'));
});

test('orders pagination retains search and stays within the seller shop', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $otherMerchant = Merchant::factory()->create();

    foreach (range(1, 12) as $index) {
        $product = Product::factory()->create(['merchant_id' => $merchant->id]);
        $quote = Quote::factory()->create(['product_id' => $product->id]);
        Order::factory()->create([
            'quote_id' => $quote->id,
            'merchant_id' => $merchant->id,
            'customer_name' => 'Buyer Audio '.$index,
        ]);
    }

    $otherProduct = Product::factory()->create(['merchant_id' => $otherMerchant->id]);
    $otherQuote = Quote::factory()->create(['product_id' => $otherProduct->id]);
    Order::factory()->create([
        'quote_id' => $otherQuote->id,
        'merchant_id' => $otherMerchant->id,
        'customer_name' => 'Buyer Audio outsider',
    ]);

    $this->actingAs($seller)
        ->get('/orders?q=Buyer%20Audio&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/index')
            ->has('orders.data', 2)
            ->where('orders.current_page', 2)
            ->where('orders.total', 12)
            ->where('filters.q', 'Buyer Audio'));
});

test('offers pagination retains status and stays within the seller shop', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $otherMerchant = Merchant::factory()->create();
    $products = Product::factory()->count(12)->create(['merchant_id' => $merchant->id]);

    foreach ($products as $product) {
        Quote::factory()->create([
            'product_id' => $product->id,
            'source' => 'offer',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);
    }

    $otherProduct = Product::factory()->create(['merchant_id' => $otherMerchant->id]);
    Quote::factory()->create([
        'product_id' => $otherProduct->id,
        'source' => 'offer',
        'status' => 'pending',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($seller)
        ->get('/offers?status=open&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offers/index')
            ->has('offers.data', 2)
            ->where('offers.current_page', 2)
            ->where('offers.total', 12)
            ->where('filters.status', 'open'));
});

test('notifications pagination is newest first and exposes a real second page', function () {
    $seller = User::factory()->create();
    Merchant::factory()->create(['user_id' => $seller->id]);

    foreach (range(1, 12) as $index) {
        DatabaseNotification::query()->create([
            'id' => (string) str()->uuid(),
            'type' => 'App\\Notifications\\OrderPaid',
            'notifiable_type' => User::class,
            'notifiable_id' => $seller->id,
            'data' => ['title' => 'Order '.$index, 'body' => 'Payment captured.'],
            'read_at' => null,
            'created_at' => now()->addSeconds($index),
            'updated_at' => now()->addSeconds($index),
        ]);
    }

    $this->actingAs($seller)
        ->get('/notifications?page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications.data', 2)
            ->where('notifications.current_page', 2)
            ->where('notifications.total', 12)
            ->where('notifications.data.0.title', 'Order 2'));
});

test('notifications render as read after opening marks them read', function () {
    $seller = User::factory()->create();
    Merchant::factory()->create(['user_id' => $seller->id]);
    $notification = DatabaseNotification::query()->create([
        'id' => (string) str()->uuid(),
        'type' => 'App\\Notifications\\OrderPaid',
        'notifiable_type' => User::class,
        'notifiable_id' => $seller->id,
        'data' => [
            'title' => 'Payment captured',
            'body' => 'OP-TEST · 250.00 USD',
            'href' => '/orders',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($seller)
        ->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('notifications.data.0.title', 'Payment captured')
            ->where('notifications.data.0.href', '/orders')
            ->where('notifications.data.0.read', true));

    expect($notification->fresh()->read_at)->not->toBeNull();
});
