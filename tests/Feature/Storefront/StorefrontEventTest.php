<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Http\Controllers\Storefront\CommerceCheckoutController;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Services\CartService;
use App\Services\StorefrontExperiment;
use Illuminate\Http\Request;
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
 * A fresh session-bound request for exercising the experiment service directly.
 */
function eventRequest(string $userAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1'): Request
{
    $session = app('session')->driver();
    $session->start();

    $request = Request::create('/store', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $userAgent,
    ]);
    $request->setLaravelSession($session);

    return $request;
}

test('an allowlisted event is persisted with its merchant, arm and device class', function () {
    $merchant = Merchant::factory()->create();

    $request = eventRequest();
    $request->session()->put(StorefrontExperiment::SESSION_KEY, 'treatment');

    app(StorefrontExperiment::class)->record($request, 'assistant_open');

    $event = StorefrontEvent::query()->firstOrFail();

    expect($event->name)->toBe('assistant_open')
        ->and($event->merchant_id)->toBe($merchant->id)
        ->and($event->variant)->toBe('treatment')
        ->and($event->device_class)->toBe('mobile');
});

test('an unknown event name is ignored', function () {
    Merchant::factory()->create();

    app(StorefrontExperiment::class)->record(eventRequest(), 'something_else');

    expect(StorefrontEvent::query()->count())->toBe(0);
});

test('a known event records no variant when the session is unassigned', function () {
    Merchant::factory()->create();

    app(StorefrontExperiment::class)->record(eventRequest(), 'assistant_open');

    expect(StorefrontEvent::query()->firstOrFail()->variant)->toBeNull();
});

test('only allowlisted categorical properties are stored', function () {
    Merchant::factory()->create();

    app(StorefrontExperiment::class)->record(eventRequest(), 'cart_action', [
        'action' => 'add',
        'intent' => 'offer',
        'email' => 'buyer@example.com',
        'amount_cents' => 25_000,
    ]);

    expect(StorefrontEvent::query()->firstOrFail()->properties)->toBe([
        'intent' => 'offer',
        'action' => 'add',
    ]);
});

test('an out-of-allowlist value for an allowlisted property is dropped', function () {
    Merchant::factory()->create();

    app(StorefrontExperiment::class)->record(eventRequest(), 'assistant_open', [
        'intent' => 'anything-i-want',
    ]);

    expect(StorefrontEvent::query()->firstOrFail()->properties)->toBe([]);
});

test('private and identifying fields are never stored on an event', function (array $properties) {
    Merchant::factory()->create();

    app(StorefrontExperiment::class)->record(eventRequest(), 'storefront_entry', $properties);

    expect(StorefrontEvent::query()->firstOrFail()->properties)->toBe([]);
})->with([
    'message text' => [['message' => 'I want a discount']],
    'email' => [['email' => 'buyer@example.com']],
    'customer name' => [['name' => 'Jane Buyer']],
    'address' => [['address' => '1 Test Street']],
    'quote id' => [['quote_id' => '01ABCDEFGH']],
    'sku' => [['sku' => 'HEADPHONES-PRO']],
    'product name' => [['product_name' => 'Studio Headphones']],
    'floor price' => [['floor_price_cents' => 21_000]],
    'unit cost' => [['unit_cost_cents' => 8_500]],
    'amount' => [['amount_cents' => 25_000]],
    'currency' => [['currency' => 'USD']],
    'session id' => [['session_id' => 'abc123']],
    'conversation id' => [['conversation_id' => 'conv-1']],
    'user id' => [['user_id' => 42]],
]);

test('the client event endpoint rejects a server-only event name', function () {
    Merchant::factory()->create();

    $this->postJson('/store/events', ['name' => 'purchase_verified'])->assertUnprocessable();

    expect(StorefrontEvent::query()->count())->toBe(0);
});

test('the client event endpoint records a sanitized assistant-open event', function () {
    Merchant::factory()->create();

    $this->postJson('/store/events', [
        'name' => 'assistant_open',
        'properties' => ['intent' => 'offer'],
    ])->assertOk();

    $event = StorefrontEvent::query()->firstOrFail();

    expect($event->name)->toBe('assistant_open')
        ->and($event->properties)->toBe(['intent' => 'offer']);
});

test('the client event endpoint rejects unexpected or unbounded properties', function (array $properties) {
    Merchant::factory()->create();

    $this->postJson('/store/events', [
        'name' => 'assistant_open',
        'properties' => $properties,
    ])->assertUnprocessable();

    expect(StorefrontEvent::query()->count())->toBe(0);
})->with([
    'PII property' => [['email' => 'buyer@example.com']],
    'invalid category' => [['intent' => 'private prompt text']],
    'nested property' => [['intent' => ['offer']]],
    'too many properties' => [['intent' => 'offer', 'action' => 'remove']],
]);

test('an assistant message turn records an assistant_message event', function () {
    Merchant::factory()->create();
    config(['ai.conversations.generate_title' => false]);

    StorefrontAssistant::fake(['Here are some options.']);

    $this->postJson(route('store.assistant'), ['message' => 'Any microphones?'])->assertOk();

    expect(StorefrontEvent::query()->where('name', 'assistant_message')->count())->toBe(1);
});

test('confirming a ceiling records a ceiling_confirmed event', function () {
    $merchant = Merchant::factory()->create();
    Product::factory()->create(['merchant_id' => $merchant->id, 'sku' => 'MIC-1', 'stock_quantity' => 5]);

    $this->postJson(route('store.assistant'), [
        'confirmation_action' => 'confirm_ceiling',
        'product_sku' => 'MIC-1',
        'ceiling_price_cents' => 20_000,
    ])->assertOk();

    expect(StorefrontEvent::query()->where('name', 'ceiling_confirmed')->count())->toBe(1);
});

test('adding to the cart records a cart_action event', function () {
    $merchant = Merchant::factory()->create();
    Product::factory()->create(['merchant_id' => $merchant->id, 'sku' => 'MIC-1', 'stock_quantity' => 5]);

    $this->postJson('/cart/items', ['sku' => 'MIC-1', 'quantity' => 1])->assertOk();

    $event = StorefrontEvent::query()->where('name', 'cart_action')->first();

    expect($event)->not->toBeNull()
        ->and($event->properties)->toBe(['action' => 'add']);
});

test('a checkout order carries the assigned experiment arm', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);
    $session->put(StorefrontExperiment::SESSION_KEY, 'treatment');

    [, , $order] = commerceCheckoutCreateOrder($session, $cart);

    expect($order->experiment_variant)->toBe('treatment');
});

test('an order from an unassigned session keeps a null arm', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);

    [, , $order] = commerceCheckoutCreateOrder($session, $cart);

    expect($order->experiment_variant)->toBeNull();
});

test('starting checkout records a checkout_start event with its physical flag', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);

    commerceCheckoutCreateOrder($session, $cart);

    $event = StorefrontEvent::query()->where('name', 'checkout_start')->first();

    expect($event)->not->toBeNull()
        ->and($event->properties)->toBe(['has_physical' => true]);
});

test('a verified capture records exactly one purchase_verified event', function () {
    commerceCheckoutFakeCapture('25.00');
    $product = Product::factory()->create(['type' => 'service', 'list_price_cents' => 2_500, 'stock_quantity' => 0]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);
    [, , , $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    app(CommerceCheckoutController::class)->capture($captureRequest);

    expect(StorefrontEvent::query()->where('name', 'purchase_verified')->count())->toBe(1);
});

test('an unverified capture does not record a purchase event', function () {
    commerceCheckoutFakeCapture('1.00');
    $product = Product::factory()->create(['type' => 'service', 'list_price_cents' => 2_500, 'stock_quantity' => 0]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);
    [, , , $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    app(CommerceCheckoutController::class)->capture($captureRequest);

    expect(StorefrontEvent::query()->where('name', 'purchase_verified')->count())->toBe(0);
});

test('a duplicate capture does not record a second purchase event', function () {
    commerceCheckoutFakeCapture('25.00');
    $product = Product::factory()->create(['type' => 'service', 'list_price_cents' => 2_500, 'stock_quantity' => 0]);

    [$session, $cart] = commerceCheckoutTestContext();
    app(CartService::class)->add($cart, $product, 1);
    [, , , $captureRequest] = commerceCheckoutCreateOrder($session, $cart);

    app(CommerceCheckoutController::class)->capture($captureRequest);
    app(CommerceCheckoutController::class)->capture($captureRequest);

    expect(StorefrontEvent::query()->where('name', 'purchase_verified')->count())->toBe(1);
});
