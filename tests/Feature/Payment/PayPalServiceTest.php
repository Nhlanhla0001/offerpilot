<?php

use App\Services\PayPalService;
use Illuminate\Http\Client\Request;
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

/**
 * Build a PayPal capture response with the given status, amount and currency.
 *
 * @return array<string, mixed>
 */
function paypalCapture(
    string $value = '220.00',
    string $currency = 'USD',
    string $status = 'COMPLETED',
): array {
    return [
        'id' => 'PAYPAL-ORDER-TEST',
        'status' => $status,
        'purchase_units' => [[
            'payments' => [
                'captures' => [[
                    'id' => 'CAPTURE-TEST',
                    'status' => $status,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $value,
                    ],
                ]],
            ],
        ]],
    ];
}

test('requests an access token once and reuses it', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 32400,
        ]),
    ]);

    $service = app(PayPalService::class);

    expect($service->accessToken())->toBe('test-token')
        ->and($service->accessToken())->toBe('test-token');

    Http::assertSentCount(1);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
            && $request->hasHeader('Authorization')
            && $request['grant_type'] === 'client_credentials';
    });
});

test('creates an order with the amount in major units', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-ORDER-TEST',
            'status' => 'CREATED',
        ]),
    ]);

    $order = app(PayPalService::class)->createOrder(22_000, 'quote-123', 'Studio Headphones');

    expect($order['id'])->toBe('PAYPAL-ORDER-TEST');

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/v2/checkout/orders')) {
            return false;
        }

        return $request['intent'] === 'CAPTURE'
            && $request['purchase_units'][0]['amount']['value'] === '220.00'
            && $request['purchase_units'][0]['amount']['currency_code'] === 'USD'
            && $request['purchase_units'][0]['reference_id'] === 'quote-123';
    });
});

test('verifies a completed capture for the exact expected amount', function () {
    $service = app(PayPalService::class);

    expect($service->captureIsVerified(paypalCapture(), 22_000))->toBeTrue()
        ->and($service->captureId(paypalCapture()))->toBe('CAPTURE-TEST');
});

test('rejects a capture whose amount does not match the quote', function () {
    $service = app(PayPalService::class);

    expect($service->captureIsVerified(paypalCapture('1.00'), 22_000))->toBeFalse();
});

test('rejects a capture that is not completed', function () {
    $service = app(PayPalService::class);

    expect($service->captureIsVerified(paypalCapture('220.00', 'USD', 'PENDING'), 22_000))->toBeFalse();
});

test('rejects a capture in the wrong currency', function () {
    $service = app(PayPalService::class);

    expect($service->captureIsVerified(paypalCapture('220.00', 'EUR'), 22_000))->toBeFalse();
});

test('captures an approved order', function () {
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'test-token']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/*/capture' => Http::response(paypalCapture()),
    ]);

    $capture = app(PayPalService::class)->captureOrder('PAYPAL-ORDER-TEST');

    expect($capture['status'])->toBe('COMPLETED');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v2/checkout/orders/PAYPAL-ORDER-TEST/capture')
        && str_contains((string) ($request->header('Content-Type')[0] ?? ''), 'application/json'));
});

test('fails loudly when PayPal credentials are missing', function () {
    config(['services.paypal.client_id' => null]);

    expect(fn () => app(PayPalService::class)->accessToken())
        ->toThrow(RuntimeException::class, 'PayPal is not configured');
});
