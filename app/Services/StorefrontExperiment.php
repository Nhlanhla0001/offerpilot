<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\StorefrontEvent;
use Illuminate\Http\Request;

/**
 * Owns storefront experiment assignment and PII-free first-party events.
 *
 * Assignment is an intent-to-treat split: the first eligible storefront visit
 * fixes an arm in the session, and every later event and order carries that arm.
 * Events never store identifiers, message text, payment data, or seller rules.
 */
class StorefrontExperiment
{
    /**
     * The session key holding this browser's stable experiment arm.
     */
    public const SESSION_KEY = 'storefront_experiment_variant';

    public const CONTROL = 'control';

    public const TREATMENT = 'treatment';

    /**
     * The only event names that may be recorded.
     *
     * @var list<string>
     */
    private const EVENT_NAMES = [
        'storefront_entry',
        'assistant_open',
        'assistant_message',
        'ceiling_confirmed',
        'cart_action',
        'checkout_start',
        'purchase_verified',
    ];

    /**
     * The only event names a browser may submit on the client event endpoint.
     *
     * @var list<string>
     */
    public const CLIENT_EVENT_NAMES = [
        'assistant_open',
    ];

    /**
     * The only property keys accepted, each with its allowlisted categorical values.
     *
     * @var array<string, list<mixed>>
     */
    private const PROPERTY_SCHEMA = [
        'intent' => ['question', 'offer'],
        'action' => ['add', 'update', 'remove'],
        'has_physical' => [true, false],
    ];

    /**
     * The possible experiment arms.
     *
     * @var list<string>
     */
    private const VARIANTS = [self::CONTROL, self::TREATMENT];

    /**
     * Assign this browser a stable arm, recording the ITT denominator once.
     *
     * Sellers and requests with no published merchant are excluded and fall back
     * to the safe control arm without being recorded or persisted.
     */
    public function assign(Request $request): string
    {
        if (! $this->isEligible($request)) {
            return self::CONTROL;
        }

        $variant = $request->session()->get(self::SESSION_KEY);

        if (is_string($variant) && in_array($variant, self::VARIANTS, true)) {
            return $variant;
        }

        $variant = $this->isActive() ? $this->randomVariant() : self::CONTROL;
        $request->session()->put(self::SESSION_KEY, $variant);

        $this->record($request, 'storefront_entry');

        return $variant;
    }

    /**
     * Get the arm already assigned to this browser, or null when unassigned.
     */
    public function currentVariant(Request $request): ?string
    {
        $variant = $request->session()->get(self::SESSION_KEY);

        return is_string($variant) && in_array($variant, self::VARIANTS, true)
            ? $variant
            : null;
    }

    /**
     * Persist a PII-free event if its name and properties are allowlisted.
     *
     * Unknown event names are ignored. Properties are reduced to the allowlisted
     * categorical values, so arbitrary or private strings can never be stored.
     */
    public function record(Request $request, string $name, array $properties = []): void
    {
        if (! in_array($name, self::EVENT_NAMES, true)) {
            return;
        }

        $merchant = $this->merchant();

        if ($merchant === null) {
            return;
        }

        StorefrontEvent::create([
            'name' => $name,
            'variant' => $this->currentVariant($request),
            'merchant_id' => $merchant->id,
            'device_class' => $this->deviceClass($request),
            'properties' => $this->sanitizeProperties($properties),
        ]);
    }

    /**
     * Determine whether this request belongs to the buyer experiment.
     */
    private function isEligible(Request $request): bool
    {
        if ($request->user()?->merchant !== null) {
            return false;
        }

        return $this->merchant() !== null;
    }

    /**
     * Resolve the single storefront merchant, mirroring ProductController.
     */
    private function merchant(): ?Merchant
    {
        return Merchant::query()->orderBy('id')->first();
    }

    /**
     * Determine whether the experiment is live.
     */
    private function isActive(): bool
    {
        return (bool) config('storefront.experiment.active', false);
    }

    /**
     * Draw one arm at random for a fresh 50/50 assignment.
     */
    protected function randomVariant(): string
    {
        return random_int(0, 1) === 0 ? self::CONTROL : self::TREATMENT;
    }

    /**
     * Reduce a property payload to only allowlisted categorical values.
     *
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];

        foreach (self::PROPERTY_SCHEMA as $key => $allowed) {
            if (! array_key_exists($key, $properties)) {
                continue;
            }

            if (in_array($properties[$key], $allowed, true)) {
                $sanitized[$key] = $properties[$key];
            }
        }

        return $sanitized;
    }

    /**
     * Classify the request's device from its user agent, without storing the agent.
     */
    private function deviceClass(Request $request): ?string
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return null;
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return 'tablet';
        }

        if (
            str_contains($agent, 'mobile')
            || str_contains($agent, 'iphone')
            || str_contains($agent, 'android')
        ) {
            return 'mobile';
        }

        return 'desktop';
    }
}
