<?php

namespace App\Ai\Tools;

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
 * The agent's only route to a price.
 *
 * The tool runs the deterministic NegotiationService, and persists a server-issued
 * quote whenever the merchant will honour a price. The merchant's floor is not an
 * argument, a result, or part of the description.
 */
class NegotiatePrice implements Tool
{
    public function __construct(
        private readonly Product $product,
        private readonly ?User $user,
        private readonly int $ceilingPriceCents,
        private readonly int $roundsUsed = 0,
    ) {
        //
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Ask the merchant to accept the price the buyer is willing to pay. '
            .'Returns JSON with status "accepted", "countered" or "rejected", the price '
            .'the merchant will honour, a quote_id when a price is held, and any '
            .'alternatives that fit the buyer budget. This is the only source of pricing '
            .'truth. Never state a price without calling it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'target_price_cents' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = app(NegotiationService::class)->negotiate(
                $this->product,
                (int) $validated['target_price_cents'],
                $this->ceilingPriceCents,
                $this->roundsUsed,
            );
        } catch (InvalidArgumentException $exception) {
            return json_encode([
                'status' => 'invalid',
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        $payload = $result->toArray();

        if ($result->status->isPurchasable() && $result->priceCents !== null) {
            $payload['quote_id'] = $this->issueQuote($result->priceCents)->quote_id;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * Persist a time-limited quote and retire any earlier pending quote.
     */
    private function issueQuote(int $agreedPriceCents): Quote
    {
        if ($this->user !== null) {
            Quote::query()
                ->where('user_id', $this->user->id)
                ->where('product_id', $this->product->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);
        }

        return Quote::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user?->id,
            'target_price_cents' => $agreedPriceCents,
            'ceiling_price_cents' => $this->ceilingPriceCents,
            'agreed_price_cents' => $agreedPriceCents,
            'rounds_used' => $this->roundsUsed + 1,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_price_cents' => $schema
                ->integer()
                ->min(1)
                ->description('The price the buyer is offering, in integer cents, for example 20000 for $200.00.')
                ->required(),
        ];
    }
}
