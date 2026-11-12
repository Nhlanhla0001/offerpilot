<?php

namespace App\Http\Controllers\Storefront;

use App\Ai\Agents\OfferPilotAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AssistantRequest;
use App\Http\Requests\Storefront\NegotiateRequest;
use App\Models\Product;
use App\Models\Quote;
use App\Services\BuyerSession;
use Illuminate\Http\JsonResponse;

class NegotiationController extends Controller
{
    public function __construct(private readonly BuyerSession $buyers)
    {
        //
    }

    /**
     * Send one buyer message to the negotiation agent and return its reply.
     */
    public function __invoke(NegotiateRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $guestToken = $this->buyers->tokenForQuote($request);

        $targetPriceCents = isset($validated['target_price_cents'])
            ? (int) $validated['target_price_cents']
            : null;

        $buyerKey = $user !== null ? 'user-'.$user->id : 'guest-'.$guestToken;
        $confirmations = $request->session()->get(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY, []);
        $authorization = is_array($confirmations)
            ? ($confirmations[$buyerKey][$product->sku] ?? null)
            : null;

        if (
            ! is_array($authorization)
            || (int) ($authorization['product_id'] ?? 0) !== $product->id
            || ! is_numeric($authorization['ceiling_price_cents'] ?? null)
            || (int) $authorization['ceiling_price_cents'] <= 0
        ) {
            return response()->json([
                'message' => 'Please confirm your maximum in the shop before negotiating.',
            ], 422);
        }

        $ceilingPriceCents = (int) $authorization['ceiling_price_cents'];

        $roundsUsed = $this->buyers->scope(Quote::query(), $request)
            ->where('product_id', $product->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->count();

        $lastQuoteId = $this->buyers->scope(Quote::query(), $request)
            ->where('product_id', $product->id)
            ->max('id') ?? 0;

        $agent = new OfferPilotAgent(
            $product,
            $targetPriceCents,
            $ceilingPriceCents,
            $roundsUsed,
            $user,
            $guestToken,
        );

        $reply = $user !== null
            ? (string) $agent->forUser($user)->prompt($validated['message'])
            : (string) $agent->prompt($validated['message']);

        $quote = $this->buyers->scope(Quote::query(), $request)
            ->where('product_id', $product->id)
            ->where('id', '>', $lastQuoteId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('id')
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
