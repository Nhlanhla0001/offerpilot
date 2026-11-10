<?php

use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('inventory search filters by product name, sku and brand', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $user->id]);

    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
    ]);

    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Desk Microphone',
        'sku' => 'MIC-DESK-01',
    ]);

    $this->actingAs($user)
        ->get('/inventory?q=microphone')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/index')
            ->has('products', 1)
            ->where('products.0.name', 'Desk Microphone')
            ->where('filters.q', 'microphone'));
});

test('orders search filters by order number and customer', function () {
    $user = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $user->id]);
    $product = Product::factory()->create(['merchant_id' => $merchant->id]);

    $thandi = Quote::factory()->create(['product_id' => $product->id]);
    $sipho = Quote::factory()->create(['product_id' => $product->id]);

    Order::factory()->create([
        'quote_id' => $thandi->id,
        'merchant_id' => $merchant->id,
        'order_number' => 'OP-THANDI-0001',
        'customer_name' => 'Thandi Buyer',
    ]);

    Order::factory()->create([
        'quote_id' => $sipho->id,
        'merchant_id' => $merchant->id,
        'order_number' => 'OP-SIPHO-0002',
        'customer_name' => 'Sipho Ndlovu',
    ]);

    $this->actingAs($user)
        ->get('/orders?q=thandi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/index')
            ->has('orders', 1)
            ->where('orders.0.order_number', 'OP-THANDI-0001'));
});

test('a shopper without a shop is sent to the storefront instead of the seller dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/store');

    $this->actingAs($user)
        ->get('/store')
        ->assertInertia(fn (Assert $page) => $page->where('role', 'buyer'));

    $this->actingAs($user)
        ->get('/orders')
        ->assertRedirect('/store');
});

test('a shop owner lands on the seller dashboard and is labelled a seller', function () {
    $user = User::factory()->create();
    Merchant::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seller/dashboard'));

    $this->actingAs($user)
        ->get('/store')
        ->assertInertia(fn (Assert $page) => $page->where('role', 'seller'));
});

test('a guest browsing the storefront is labelled a guest', function () {
    $this->get('/store')
        ->assertInertia(fn (Assert $page) => $page->where('role', 'guest'));
});
