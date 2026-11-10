<?php

use App\Ai\Agents\OfferPilotAgent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quote;
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
        'stock_quantity' => 2,
    ]);

    $quote = $this->postJson(route('store.buy', $product->sku))->json('quote');

    $response = $this->postJson(route('store.checkout', $product->sku), [
        'quote' => $quote['id'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('paypalOrderId', 'PAYPAL-GUEST-ORDER');
    $response->assertJsonPath('amount', '120.00');
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

test('a guest can talk to the assistant without an account', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    OfferPilotAgent::fake(['It ships with a cable and a carry pouch.']);

    $response = $this->postJson(route('store.negotiate', $product->sku), [
        'message' => 'What is included in the box?',
    ]);

    $response->assertOk();
    $response->assertJsonPath('quote', null);

    expect(session('buyer_token'))->not->toBeNull();
});
