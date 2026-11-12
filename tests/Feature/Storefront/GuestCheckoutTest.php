<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
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
});

test('a guest can start a buy-now quote that is bound to their session', function () {
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 3,
    ]);

    $response = $this->postJson(route('store.buy', $product->sku));

    $response->assertOk();
    $response->assertJsonPath('quote.price', '250.00');

    $quote = Quote::query()->firstOrFail();

    expect($quote->user_id)->toBeNull()
        ->and($quote->guest_token)->not->toBeNull()
        ->and($quote->guest_token)->toBe(session('buyer_token'));
});

test('a guest can create a paypal order for their own quote', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-GUEST-ORDER',
            'status' => 'CREATED',
        ]),
    ]);

    $product = Product::factory()->create([
        'list_price_cents' => 12_000,
        'unit_cost_cents' => 4_600,
        'stock_quantity' => 2,
    ]);

    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');

    $response = $this->postJson(route('store.checkout', $product->sku), [
        'quote' => $quote['id'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('paypalOrderId', 'PAYPAL-GUEST-ORDER');
    $response->assertJsonPath('amount', '120.00');

    $order = Order::query()->with('items')->firstOrFail();
    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->unit_cost_cents)->toBe(4_600);
});

test('a guest cannot check out a quote issued to another browser', function () {
    Http::fake();

    $product = Product::factory()->create(['list_price_cents' => 25_000]);

    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');

    $this->flushSession();

    $response = $this->postJson(route('store.checkout', $product->sku), [
        'quote' => $quote['id'],
    ]);

    $response->assertNotFound();

    Http::assertNothingSent();
});

test('a guest cannot capture another browser order', function () {
    Http::fake();

    $product = Product::factory()->create(['list_price_cents' => 25_000]);

    $quote = Quote::factory()->create([
        'user_id' => null,
        'guest_token' => 'someone-elses-session',
        'product_id' => $product->id,
        'agreed_price_cents' => 25_000,
        'ceiling_price_cents' => 25_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);

    $order = Order::factory()->create([
        'quote_id' => $quote->id,
        'merchant_id' => $product->merchant_id,
        'paypal_order_id' => 'PAYPAL-SOMEONE-ELSE',
    ]);

    $response = $this->postJson(route('checkout.capture'), [
        'paypal_order_id' => $order->paypal_order_id,
    ]);

    $response->assertForbidden();

    Http::assertNothingSent();
});

test('capturing a service order does not decrement physical stock', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-SERVICE-ORDER',
            'status' => 'CREATED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-SERVICE-ORDER/capture' => Http::response([
            'id' => 'PAYPAL-SERVICE-ORDER',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-SERVICE-ORDER',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '25.00'],
                ]]],
            ]],
        ]),
    ]);
    $product = Product::factory()->create([
        'type' => 'service',
        'list_price_cents' => 2_500,
        'stock_quantity' => 0,
    ]);
    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');
    $order = $this->postJson(route('store.checkout', $product->sku), ['quote' => $quote['id']])
        ->json('paypalOrderId');

    $this->postJson(route('checkout.capture'), ['paypal_order_id' => $order])->assertOk();

    expect($product->refresh()->stock_quantity)->toBe(0);
});

test('a buy-now capture is refused when physical stock runs out before capture', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-BUY-NOW-STOCK',
            'status' => 'CREATED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-BUY-NOW-STOCK/capture' => Http::response([
            'id' => 'PAYPAL-BUY-NOW-STOCK',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-BUY-NOW-STOCK',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '250.00'],
                ]]],
            ]],
        ]),
    ]);
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 1,
    ]);
    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');
    $orderId = $this->postJson(route('store.checkout', $product->sku), [
        'quote' => $quote['id'],
    ])->assertOk()->json('paypalOrderId');
    $order = Order::query()->where('paypal_order_id', $orderId)->firstOrFail();
    $product->update(['stock_quantity' => 0]);

    $this->postJson(route('checkout.capture'), ['paypal_order_id' => $orderId])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only 0 of '.$product->name.' are left. Your payment was not captured.');

    expect($order->fresh()->status->value)->toBe('created')
        ->and($product->refresh()->stock_quantity)->toBe(0);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/capture'));
});

test('a verified buy-now capture notifies the shop owner', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-BUY-NOW-NOTIFICATION',
            'status' => 'CREATED',
        ]),
        'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-BUY-NOW-NOTIFICATION/capture' => Http::response([
            'id' => 'PAYPAL-BUY-NOW-NOTIFICATION',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-BUY-NOW-NOTIFICATION',
                    'status' => 'COMPLETED',
                    'amount' => ['currency_code' => 'USD', 'value' => '250.00'],
                ]]],
            ]],
        ]),
    ]);
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'list_price_cents' => 25_000,
        'stock_quantity' => 2,
    ]);
    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');
    $orderId = $this->postJson(route('store.checkout', $product->sku), [
        'quote' => $quote['id'],
    ])->assertOk()->json('paypalOrderId');

    $this->postJson(route('checkout.capture'), ['paypal_order_id' => $orderId])
        ->assertOk()
        ->assertJsonPath('status', 'paid');

    $notification = $seller->notifications()->firstOrFail();
    expect($notification->data['title'])->toBe('Payment captured')
        ->and($notification->data['href'])->toBe('/orders')
        ->and($product->refresh()->stock_quantity)->toBe(1);
});

test('a guest can talk to the assistant without an account', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    StorefrontAssistant::fake(['It ships with a cable and a carry pouch.']);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'What is included in the box?',
        'product_sku' => $product->sku,
    ]);

    $response->assertOk();
    $response->assertJsonPath('quote', null);

    expect(session('buyer_token'))->not->toBeNull();
});

test('a guest cannot apply another guest session quote through the cart endpoint', function () {
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 3,
    ]);
    $quote = Quote::factory()->create([
        'product_id' => $product->id,
        'guest_token' => 'another-buyers-session',
        'agreed_price_cents' => 21_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->postJson(route('cart.items.store'), [
        'sku' => $product->sku,
        'quote' => $quote->quote_id,
    ]);

    $response->assertOk()
        ->assertJsonPath('items.0.negotiated', false)
        ->assertJsonPath('items.0.unit_price', '250.00');
});
