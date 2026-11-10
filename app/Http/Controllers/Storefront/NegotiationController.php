<?php

namespace App\Http\Controllers\Storefront;

use App\Ai\Agents\OfferPilotAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\NegotiateRequest;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;

class NegotiationController extends Controller
{
    /**
     * Send one buyer message to the negotiation agent and return its reply.
     */
    public function __invoke(NegotiateRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $targetPriceCents = (int) $validated['target_price_cents'];
        $ceilingPriceCents = (int) $validated['ceiling_price_cents'];

        $roundsUsed = Quote::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->count();

        $agent = new OfferPilotAgent(
            $product,
            $targetPriceCents,
            $ceilingPriceCents,
            $roundsUsed,
            $user,
        );

        $reply = (string) $agent->forUser($user)->prompt($validated['message']);

        $quote = Quote::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return response()->json([
            'reply' => $reply,
            'quote' => $quote === null ? null : [
                'id' => $quote->quote_id,
                'price' => $quote->formattedPrice(),
                'expiresAt' => $quote->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
