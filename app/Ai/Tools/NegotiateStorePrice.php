<?php

namespace App\Ai\Tools;

use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\NegotiationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The storefront assistant's only route to a price.
 *
 * The tool runs the deterministic NegotiationService, and only then persists a
 * server-issued quote whenever the merchant will honour a price. Negotiation
 * never happens without a buyer maximum injected from server-side session
 * authorization for the exact SKU. The model cannot provide or change it. The
 * merchant's floor is not an argument, a result, or part of the description.
 */
class NegotiateStorePrice implements Tool
{
    public function __construct(
        private readonly Merchant $merchant,
        private readonly ?User $user,
        private readonly ?string $guestToken = null,
        private readonly ?string $authorizedSku = null,
        private readonly ?int $authorizedCeilingCents = null,
    ) {
        //
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Negotiate the price of a product in this shop for the buyer. '
            .'Requires the product SKU and the buyer\'s target offer in integer cents. The buyer\'s '
            .'maximum is supplied securely by the shop only after explicit buyer confirmation; it is '
            .'not a tool argument. If no confirmed maximum is available, ask the buyer to use the '
            .'shop\'s maximum-confirmation control before calling. '
            .'Returns JSON with status "accepted", "countered" or "rejected", the price the merchant '
            .'will honour, a quote_id when a price is held, and any alternatives that fit the buyer '
            .'budget. This is the only source of pricing truth; never state a price without calling it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'target_price_cents' => ['required', 'integer', 'min:1'],
        ]);

        if (
            $this->authorizedCeilingCents === null
            || $this->authorizedCeilingCents <= 0
            || $this->authorizedSku !== (string) $validated['sku']
        ) {
            return json_encode([
                'status' => 'clarification',
                'message' => 'The buyer has not confirmed a maximum for this product. '
                    .'Ask them to use the shop\'s maximum-confirmation control before negotiating.',
            ], JSON_THROW_ON_ERROR);
        }

        $ceiling = $this->authorizedCeilingCents;

        $product = $this->merchant->products()
            ->inStock()
            ->where('sku', (string) $validated['sku'])
            ->first();

        if ($product === null) {
            return json_encode([
                'status' => 'clarification',
                'message' => 'This product is not available in this shop.',
            ], JSON_THROW_ON_ERROR);
        }

        $roundsUsed = Quote::query()
            ->where($this->ownership())
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();

        try {
            $result = app(NegotiationService::class)->negotiate(
                $product,
                (int) $validated['target_price_cents'],
                $ceiling,
                $roundsUsed,
            );
        } catch (InvalidArgumentException $exception) {
            return json_encode([
                'status' => 'clarification',
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $payload = $result->toArray();

        if ($result->status->isPurchasable() && $result->priceCents !== null) {
            $payload['quote_id'] = $this->issueQuote(
                $product,
                (int) $validated['target_price_cents'],
                $ceiling,
                $result->priceCents,
                $roundsUsed,
            )->quote_id;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sku' => $schema->string()
                ->min(1)
                ->max(100)
                ->description('The SKU of the product the buyer is negotiating.')
                ->required(),
            'target_price_cents' => $schema->integer()
                ->min(1)
                ->description('The price the buyer is offering, in integer cents, for example 20000 for $200.00.')
                ->required(),
        ];
    }

    /**
     * Persist a time-limited quote carrying the confirmed maximum, retiring any earlier pending quote.
     */
    private function issueQuote(
        Product $product,
        int $targetPriceCents,
        int $ceilingPriceCents,
        int $agreedPriceCents,
        int $roundsUsed,
    ): Quote {
        $ownership = $this->ownership();

        Quote::query()
            ->where('product_id', $product->id)
            ->where($ownership)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        return Quote::create([
            'product_id' => $product->id,
            'user_id' => $this->user?->id,
            'guest_token' => $this->user === null ? $this->guestToken : null,
            'source' => 'offer',
            'target_price_cents' => $targetPriceCents,
            'ceiling_price_cents' => $ceilingPriceCents,
            'agreed_price_cents' => $agreedPriceCents,
            'rounds_used' => $roundsUsed + 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * The quote-ownership key for the current buyer.
     *
     * @return array<string, mixed>
     */
    private function ownership(): array
    {
        return $this->user !== null
            ? ['user_id' => $this->user->id]
            : ['guest_token' => $this->guestToken];
    }
}
