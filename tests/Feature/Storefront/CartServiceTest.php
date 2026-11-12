<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\Quote;
use App\Services\CartService;

test('adding a product prices the line from the server, not the browser', function () {
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 3,
    ]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-one');

    $service->add($cart, $product, 2);

    $summary = $service->summary($cart->refresh());

    expect($summary['items'])->toHaveCount(1)
        ->and($summary['items'][0]['unit_price'])->toBe('250.00')
        ->and($summary['subtotal_cents'])->toBe(50_000)
        ->and($summary['shipping_cents'])->toBe(CartService::FLAT_SHIPPING_CENTS)
        ->and($summary['total_cents'])->toBe(51_500)
        ->and($summary['count'])->toBe(2);
});

test('a cart line cannot exceed available stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 2]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-two');

    $item = $service->add($cart, $product, 9);

    expect($item->quantity)->toBe(2);
});

test('an expired quote falls back to the listed price', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000, 'stock_quantity' => 4]);

    $quote = Quote::factory()->expired()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 21_000,
    ]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-three');

    $service->add($cart, $product, 1, $quote);

    $summary = $service->summary($cart->refresh());

    expect($summary['items'][0]['unit_price'])->toBe('250.00')
        ->and($summary['items'][0]['negotiated'])->toBeFalse();
});

test('a valid quote is honoured in the cart', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000, 'stock_quantity' => 4]);

    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 22_500,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-four');

    $service->add($cart, $product, 1, $quote);

    $summary = $service->summary($cart->refresh());

    expect($summary['items'][0]['unit_price'])->toBe('225.00')
        ->and($summary['items'][0]['negotiated'])->toBeTrue();
});

test('a negotiated quote is held for one unit only', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000, 'stock_quantity' => 5]);

    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 22_500,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-quote-quantity');

    $service->add($cart, $product, 4, $quote);
    $service->add($cart, $product, 4, $quote);

    $summary = $service->summary($cart->refresh());

    expect($summary['items'][0]['quantity'])->toBe(1)
        ->and($summary['items'][0]['unit_price'])->toBe('225.00')
        ->and($summary['count'])->toBe(1);
});

test('a negotiated cart line cannot be increased beyond its quoted unit', function () {
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 22_500,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);
    $service = app(CartService::class);
    $cart = $service->forSession('session-quoted-line-quantity');
    $item = $service->add($cart, $product, 1, $quote);

    $service->setQuantity($item, 4);
    $summary = $service->summary($cart->refresh());

    expect($summary['items'][0]['quantity'])->toBe(1)
        ->and($summary['items'][0]['max_quantity'])->toBe(1)
        ->and($summary['items'][0]['unit_price'])->toBe('225.00')
        ->and($summary['items'][0]['negotiated'])->toBeTrue();
});

test('accepting a quote reprices a line that was already in the cart', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000, 'stock_quantity' => 5]);

    $service = app(CartService::class);
    $cart = $service->forSession('session-quote-reprice');

    $service->add($cart, $product, 3);

    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 22_500,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $service->add($cart, $product, 1, $quote);

    $summary = $service->summary($cart->refresh());

    expect($summary['items'][0]['quantity'])->toBe(1)
        ->and($summary['items'][0]['unit_price'])->toBe('225.00')
        ->and($summary['items'][0]['negotiated'])->toBeTrue();
});

test('carts are isolated by session', function () {
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    $service = app(CartService::class);

    $service->add($service->forSession('session-a'), $first);
    $service->add($service->forSession('session-b'), $second);

    expect(Cart::query()->where('session_id', 'session-a')->first()->items)->toHaveCount(1)
        ->and(Cart::query()->where('session_id', 'session-b')->first()->items)->toHaveCount(1)
        ->and($service->summary($service->forSession('session-a'))['items'][0]['sku'])->toBe($first->sku);
});
