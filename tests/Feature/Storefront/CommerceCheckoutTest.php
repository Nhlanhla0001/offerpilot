<?php

use App\Http\Controllers\Storefront\CommerceCheckoutController;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();

    config([
        'services.paypal.mode' => 'sandbox',
        'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
        'services.paypal.client_id' => 'test-client-id',
        'services.paypal.client_secret' => 'test-client-secret',
        'services.paypal.currency' => 'USD',
    ]);

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-CART-CHECKOUT',
            'status' => 'CREATED',
        ]),
    ]);
});

/**
 * Create a buyer checkout request tied to a known session cart.
 *
 * @return array{0: Store, 1: Cart, 2: Request}
 */
function commerceCheckoutTestContext(array $payload = []): array
{
    $session = app('session')->driver();
    $session->start();
    $cart = app(CartService::class)->forSession($session->getId());
    $request = Request::create('/checkout', 'POST', array_merge([
        'name' => 'Buyer Test',
        'email' => 'buyer@example.com',
        'address' => '1 Test Street',
        'city' => 'Cape Town',
        'postal_code' => '8001',
        'country' => 'South Africa',
    ], $payload));
    $request->setLaravelSession($session);

    return [$session, $cart, $request];
}

/**
 * Fake a successful PayPal capture for the supplied major-unit amount.
 */
function commerceCheckoutFakeCapture(string $amount): void
{
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-CART-CHECKOUT',
            'status' => 'CREATED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-CART-CHECKOUT/capture' => Http::response([
            'id' => 'PAYPAL-CART-CHECKOUT',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-CART-CHECKOUT',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => $amount],
                ]]],
            ]],
        ]),
    ]);
}

/**
 * Create a cart order and capture request using its original buyer session.
 *
 * @return array{0: Store, 1: Cart, 2: Order, 3: Request}
 */
function commerceCheckoutCreateOrder(Store $session, Cart $cart): array
{
    $createRequest = Request::create('/checkout', 'POST', [
        'name' => 'Buyer Test',
        'email' => 'buyer@example.com',
        'address' => '1 Test Street',
        'city' => 'Cape Town',
        'postal_code' => '8001',
        'country' => 'South Africa',
    ]);
    $createRequest->setLaravelSession($session);
    $response = app(CommerceCheckoutController::class)->store($createRequest);
    $order = Order::query()->with('items')->firstOrFail();
    $captureRequest = Request::create('/checkout/complete', 'POST', [
        'paypal_order_id' => $order->paypal_order_id,
    ]);
    $captureRequest->setLaravelSession($session);

    expect($response->getStatusCode())->toBe(200);

    return [$session, $cart, $order, $captureRequest];
}

test('cart checkout snapshots the repriced amount after a quote expires', function () {
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'unit_cost_cents' => 10_000,
        'stock_quantity' => 4,
    ]);
    $quote = Quote::factory()->expired()->create([
        'product_id' => $product->id,
        'agreed_price_cents' => 21_000,
    ]);
    [, $cart, $request] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1, $quote);
    $product->update(['list_price_cents' => 30_000]);

    $response = app(CommerceCheckoutController::class)->store($request);
    $order = Order::query()->with('items')->firstOrFail();

    expect($response->getStatusCode())->toBe(200)
        ->and($order->subtotal_cents)->toBe(30_000)
        ->and($order->items->first()->unit_price_cents)->toBe(30_000)
        ->and($order->items->first()->line_total_cents)->toBe(30_000)
        ->and($order->items->first()->unit_cost_cents)->toBe(10_000);
});

test('cart checkout creates one order for every product in the cart', function () {
    $microphone = Product::factory()->create([
        'name' => 'Desk Microphone',
        'sku' => 'MIC-DM2',
        'list_price_cents' => 12_000,
        'stock_quantity' => 5,
    ]);
    $keyboard = Product::factory()->create([
        'name' => 'Mechanical Keyboard',
        'sku' => 'KEY-K1',
        'list_price_cents' => 18_500,
        'stock_quantity' => 4,
    ]);
    [, $cart, $request] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $microphone, 2);
    app(CartService::class)->add($cart, $keyboard, 1);

    $response = app(CommerceCheckoutController::class)->store($request);
    $order = Order::query()->with('items')->firstOrFail();

    expect($response->getStatusCode())->toBe(200)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->pluck('sku')->all())->toBe(['MIC-DM2', 'KEY-K1'])
        ->and($order->items->sum('line_total_cents'))->toBe(42_500)
        ->and($order->amount_cents)->toBe(44_000)
        ->and($response->getData(true)['amount'])->toBe('440.00');
});

test('capturing a service order does not decrement physical stock', function () {
    commerceCheckoutFakeCapture('25.00');
    $product = Product::factory()->create([
        'type' => 'service',
        'list_price_cents' => 2_500,
        'stock_quantity' => 0,
    ]);
    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product);
    [, , $order, $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    $response = app(CommerceCheckoutController::class)->capture($captureRequest);

    expect($response->getStatusCode())->toBe(200)
        ->and($product->refresh()->stock_quantity)->toBe(0)
        ->and($order->fresh()->status->value)->toBe('paid');
});

test('a verified cart capture fulfills its negotiated quote', function () {
    commerceCheckoutFakeCapture('245.00');
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 4,
    ]);
    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'source' => 'offer',
        'agreed_price_cents' => 23_000,
        'ceiling_price_cents' => 25_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);
    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1, $quote);
    [, , , $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    $response = app(CommerceCheckoutController::class)->capture($captureRequest);

    expect($response->getStatusCode())->toBe(200)
        ->and($quote->fresh()->status->value)->toBe('fulfilled')
        ->and($product->refresh()->stock_quantity)->toBe(3);
});

test('capture refuses to charge when stock falls below the order quantity after checkout starts', function () {
    commerceCheckoutFakeCapture('65.00');
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 2,
    ]);
    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 2);
    [, , $order, $captureRequest] = commerceCheckoutCreateOrder($session, $cart);
    $product->update(['stock_quantity' => 1]);

    $response = app(CommerceCheckoutController::class)->capture($captureRequest);

    expect($response->getStatusCode())->toBe(422)
        ->and($order->fresh()->status->value)->toBe('created')
        ->and($product->refresh()->stock_quantity)->toBe(1);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/capture'));
});

test('an unverified cart capture does not fulfill offers or consume stock', function () {
    commerceCheckoutFakeCapture('1.00');
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 4,
    ]);
    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'source' => 'offer',
        'agreed_price_cents' => 23_000,
        'ceiling_price_cents' => 25_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);
    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1, $quote);
    [, , $order, $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    $response = app(CommerceCheckoutController::class)->capture($captureRequest);

    expect($response->getStatusCode())->toBe(422)
        ->and($order->fresh()->status->value)->toBe('failed')
        ->and($quote->fresh()->status->value)->toBe('pending')
        ->and($product->refresh()->stock_quantity)->toBe(4)
        ->and($cart->items()->count())->toBe(1);
});
