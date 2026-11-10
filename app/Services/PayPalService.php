<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Boundary around PayPal's Orders v2 REST API.
 *
 * The browser is never trusted: capture verification happens here, against the
 * PayPal response, before an order is marked as paid.
 */
final class PayPalService
{
    /**
     * The cache key holding the cached OAuth access token.
     */
    private const TOKEN_CACHE_KEY = 'paypal:access-token';

    public function __construct(private readonly Repository $config)
    {
        //
    }

    /**
     * Get a cached PayPal access token, requesting a new one when needed.
     */
    public function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function (): string {
            $response = $this->request()
                ->asForm()
                ->withBasicAuth($this->clientId(), $this->clientSecret())
                ->post($this->baseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('PayPal did not return an access token.');
            }

            return $token;
        });
    }

    /**
     * Create a PayPal order for a server-issued quote.
     *
     * @return array<string, mixed>
     */
    public function createOrder(int $amountCents, string $reference, string $description): array
    {
        return $this->request()
            ->withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $reference,
                    'description' => $description,
                    'amount' => [
                        'currency_code' => $this->currency(),
                        'value' => $this->toMajorUnits($amountCents),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => (string) $this->config->get('app.name'),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                ],
            ])
            ->throw()
            ->json() ?? [];
    }

    /**
     * Capture payment for an approved PayPal order.
     *
     * @return array<string, mixed>
     */
    public function captureOrder(string $paypalOrderId): array
    {
        return $this->request()
            ->withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders/'.rawurlencode($paypalOrderId).'/capture')
            ->throw()
            ->json() ?? [];
    }

    /**
     * Determine whether a capture response proves the expected amount was paid.
     *
     * @param  array<string, mixed>  $capture
     */
    public function captureIsVerified(array $capture, int $expectedAmountCents): bool
    {
        $unit = $capture['purchase_units'][0] ?? [];
        $detail = $unit['payments']['captures'][0] ?? [];

        if (($capture['status'] ?? null) !== 'COMPLETED') {
            return false;
        }

        if (($detail['status'] ?? null) !== 'COMPLETED') {
            return false;
        }

        $amount = $detail['amount'] ?? [];

        if (($amount['currency_code'] ?? null) !== $this->currency()) {
            return false;
        }

        return $this->toMinorUnits((string) ($amount['value'] ?? '0')) === $expectedAmountCents;
    }

    /**
     * Extract the capture identifier from a capture response.
     *
     * @param  array<string, mixed>  $capture
     */
    public function captureId(array $capture): ?string
    {
        $id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * Build a configured HTTP client for PayPal.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(15)
            ->retry(2, 250);
    }

    /**
     * Get the PayPal API base URL for the configured mode.
     */
    private function baseUrl(): string
    {
        return rtrim((string) $this->config->get('services.paypal.base_url'), '/');
    }

    /**
     * Get the configured PayPal client id.
     */
    private function clientId(): string
    {
        return $this->requireCredential('services.paypal.client_id');
    }

    /**
     * Get the configured PayPal client secret.
     */
    private function clientSecret(): string
    {
        return $this->requireCredential('services.paypal.client_secret');
    }

    /**
     * Get a required PayPal credential or fail loudly.
     */
    private function requireCredential(string $key): string
    {
        $value = $this->config->get($key);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("PayPal is not configured: [{$key}] is missing.");
        }

        return $value;
    }

    /**
     * Get the currency used for PayPal orders.
     */
    private function currency(): string
    {
        return (string) $this->config->get('services.paypal.currency', 'USD');
    }

    /**
     * Format minor units as PayPal's decimal amount, for example "220.00".
     */
    private function toMajorUnits(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Parse PayPal's decimal amount into minor units without floating point drift.
     */
    private function toMinorUnits(string $value): int
    {
        [$units, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return (int) $units * 100 + (int) $fraction;
    }
}
