<?php

namespace App\Http\Controllers\Storefront;

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\PresentsPublicProducts;
use App\Ai\Tools\GetStoreProduct;
use App\Ai\Tools\NegotiateStorePrice;
use App\Ai\Tools\ProposeAddToCart;
use App\Ai\Tools\SearchStoreProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AssistantRequest;
use App\Models\Cart;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\BuyerSession;
use App\Services\CartService;
use App\Services\StorefrontExperiment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolResult;

class AssistantController extends Controller
{
    use PresentsPublicProducts;

    public function __construct(
        private readonly BuyerSession $buyers,
        private readonly StorefrontExperiment $experiment,
    ) {
        //
    }

    /**
     * Send one buyer message to the shop assistant and return its reply plus
     * the public, buyer-safe state the storefront needs to render the turn.
     */
    public function __invoke(AssistantRequest $request): JsonResponse
    {
        $merchant = $request->merchant();

        if ($merchant === null) {
            return response()->json([
                'reply' => 'This shop has not published a catalogue yet.',
                'products' => [],
                'quote' => null,
                'actions' => [],
                'conversation_id' => null,
            ]);
        }

        if ($request->validated('confirmation_action') === 'confirm_ceiling') {
            return $this->confirmPriceCeiling($request);
        }

        if ($request->validated('confirmation_action') === 'confirm_quote_to_cart') {
            return $this->confirmQuoteToCart($request, $merchant);
        }

        if ($request->filled('action_id')) {
            return $this->confirmCartAction($request, $merchant);
        }

        $product = $request->product();
        $user = $request->user();
        $guestToken = $this->buyers->tokenForQuote($request);
        $cart = Cart::query()->where('session_id', $request->session()->getId())->first();

        $agent = new StorefrontAssistant(
            $merchant,
            $product,
            $user,
            $guestToken,
            $this->confirmedCeilingCents($request, $product, $user, $guestToken),
            $cart,
        );

        $this->bindConversation($request, $agent, $user);

        $response = $agent->prompt($request->validated('message'));

        if ($user === null && $response->conversationId !== null) {
            $request->session()->put(AssistantRequest::CONVERSATION_SESSION_KEY, $response->conversationId);
        }

        $quote = $this->latestPendingQuote($request, $product);

        $this->experiment->record($request, 'assistant_message');

        return response()->json([
            'reply' => (string) $response,
            'products' => $this->surfacedProducts($merchant, $product, $response),
            'quote' => $quote === null ? null : $this->quoteSummary($quote),
            'actions' => $this->actions($quote, $product, $this->cartProposals($request, $response)),
            'conversation_id' => $response->conversationId,
        ]);
    }

    /**
     * Record the exact maximum the buyer explicitly authorized for one product.
     */
    private function confirmPriceCeiling(AssistantRequest $request): JsonResponse
    {
        $product = $request->product();

        if ($product === null || ! $product->isAvailable()) {
            return response()->json(['message' => 'This product is not available right now.'], 422);
        }

        $user = $request->user();
        $guestToken = $this->buyers->tokenForQuote($request);
        $buyerKey = $this->authorizationBuyerKey($user, $guestToken);
        $confirmations = $request->session()->get(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY, []);
        $confirmations = is_array($confirmations) ? $confirmations : [];
        $confirmations[$buyerKey][$product->sku] = [
            'product_id' => $product->id,
            'ceiling_price_cents' => (int) $request->validated('ceiling_price_cents'),
        ];
        $request->session()->put(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY, $confirmations);

        $this->experiment->record($request, 'ceiling_confirmed');

        return response()->json([
            'authorization' => [
                'status' => 'confirmed',
                'product_sku' => $product->sku,
                'ceiling_price_cents' => (int) $request->validated('ceiling_price_cents'),
            ],
        ]);
    }

    /**
     * Execute a buyer-confirmed cart proposal: re-resolve everything server-side,
     * then mutate the cart through CartService.
     */
    private function confirmCartAction(AssistantRequest $request, Merchant $merchant): JsonResponse
    {
        $actionId = (string) $request->validated('action_id');
        $proposals = $request->session()->get(AssistantRequest::CART_PROPOSALS_SESSION_KEY, []);
        $proposal = is_array($proposals) ? ($proposals[$actionId] ?? null) : null;

        $proposalItems = is_array($proposal) && isset($proposal['items']) && is_array($proposal['items'])
            ? $proposal['items']
            : (is_array($proposal) && isset($proposal['sku']) ? [$proposal] : []);

        if ($proposalItems === []) {
            return response()->json([
                'message' => 'This action is no longer available. Please ask the assistant again.',
            ], 422);
        }

        $cart = app(CartService::class);
        $failure = null;
        $summary = DB::transaction(function () use ($request, $merchant, $proposalItems, $cart, &$failure): ?array {
            $resolvedItems = [];
            $seenProductIds = [];
            $existingCart = Cart::query()
                ->where('session_id', $request->session()->getId())
                ->first();
            $existingItems = $existingCart?->items()->get()->keyBy('product_id') ?? collect();
            $addedQuantities = [];

            foreach ($proposalItems as $proposalItem) {
                if (! is_array($proposalItem) || ! isset($proposalItem['sku'])) {
                    $failure = 'This action is no longer available. Please ask the assistant again.';

                    return null;
                }

                $product = $merchant->products()
                    ->where('sku', (string) $proposalItem['sku'])
                    ->lockForUpdate()
                    ->first();

                if ($product === null || ! $product->isAvailable()) {
                    $failure = count($proposalItems) > 1
                        ? 'One or more items are no longer available. Nothing was added.'
                        : 'This item is not available right now.';

                    return null;
                }

                if (isset($seenProductIds[$product->id])) {
                    $failure = 'The proposed batch contains the same item more than once. Nothing was added.';

                    return null;
                }

                $seenProductIds[$product->id] = true;

                // A quote-bound proposal must still resolve to the exact quote the
                // buyer approved. Never fall back to list price for a stale quote.
                $quoteId = $proposalItem['quote_id'] ?? null;
                $quote = $quoteId === null
                    ? null
                    : $this->resolveConfirmedQuote($request, $product, (string) $quoteId, lockForUpdate: true);

                if ($quoteId !== null && $quote === null) {
                    $failure = 'The agreed price is no longer available. Please ask the assistant again.';

                    return null;
                }

                $quantity = (int) ($proposalItem['quantity'] ?? 1);
                $maximumQuantity = $cart->ceilingFor($product);
                $existingItem = $existingItems->get($product->id);
                $totalQuantity = ($existingItem?->quantity ?? 0) + ($addedQuantities[$product->id] ?? 0) + $quantity;

                if (
                    $quantity < 1
                    || $quantity > $maximumQuantity
                    || ($quote !== null && ($quantity !== 1 || $existingItem !== null))
                    || ($quote === null && $existingItem?->quote_id !== null)
                    || ($quote === null && $totalQuantity > $maximumQuantity)
                ) {
                    $failure = count($proposalItems) > 1
                        ? 'One or more items changed in availability or quantity. Nothing was added.'
                        : "Only {$maximumQuantity} of {$product->name} are available. Nothing was added.";

                    return null;
                }

                $resolvedItems[] = [
                    'product' => $product,
                    'quote' => $quote,
                    'quantity' => $quantity,
                ];
                $addedQuantities[$product->id] = ($addedQuantities[$product->id] ?? 0) + $quantity;
            }

            $cartModel = $cart->forSession($request->session()->getId(), $request->user()?->id);

            foreach ($resolvedItems as $resolvedItem) {
                $cart->add(
                    $cartModel,
                    $resolvedItem['product'],
                    $resolvedItem['quantity'],
                    $resolvedItem['quote'],
                );
            }

            return $cart->summary($cartModel->refresh());
        });

        if ($failure !== null) {
            return response()->json(['message' => $failure], 422);
        }

        unset($proposals[$actionId]);
        $request->session()->put(AssistantRequest::CART_PROPOSALS_SESSION_KEY, $proposals);

        $this->experiment->record($request, 'cart_action', ['action' => 'add']);

        return response()->json([
            'reply' => 'Added to your cart.',
            'products' => [],
            'quote' => null,
            'actions' => [],
            'conversation_id' => $request->session()->get(AssistantRequest::CONVERSATION_SESSION_KEY),
            'cart' => $summary,
        ]);
    }

    /**
     * Turn an explicitly confirmed quote into a deterministic, single-use cart
     * proposal bound to the exact quote and product the buyer confirmed.
     *
     * This never mutates the cart: it only stages a proposal, and the buyer must
     * still confirm the returned action before anything is added.
     */
    private function confirmQuoteToCart(AssistantRequest $request, Merchant $merchant): JsonResponse
    {
        $product = $request->product();

        if ($product === null || ! $product->isAvailable()) {
            return response()->json(['message' => 'This product is not available right now.'], 422);
        }

        $quote = $this->resolveConfirmedQuote($request, $product, (string) $request->validated('quote_id'));

        if ($quote === null) {
            return response()->json(['message' => 'This price is no longer available. Please ask the assistant again.'], 422);
        }

        $actionId = (string) Str::ulid();
        $proposals = $request->session()->get(AssistantRequest::CART_PROPOSALS_SESSION_KEY, []);
        $proposals = is_array($proposals) ? $proposals : [];
        $proposals[$actionId] = [
            'items' => [[
                'sku' => $product->sku,
                'quantity' => 1,
                'quote_id' => $quote->quote_id,
            ]],
        ];
        $request->session()->put(AssistantRequest::CART_PROPOSALS_SESSION_KEY, $proposals);

        return response()->json([
            'reply' => 'Ready to add 1 × '.$product->name.' to your cart at $'.$quote->formattedPrice().'. Confirm to add it.',
            'products' => [$this->publicProductSummary($product)],
            'quote' => $this->quoteSummary($quote),
            'actions' => [[
                'type' => 'confirm_add_to_cart',
                'action_id' => $actionId,
                'sku' => $product->sku,
                'quantity' => 1,
                'quote_id' => $quote->quote_id,
                'name' => $product->name,
                'price' => $quote->formattedPrice(),
                'items' => [[
                    'sku' => $product->sku,
                    'quantity' => 1,
                    'quote_id' => $quote->quote_id,
                    'name' => $product->name,
                    'price' => $quote->formattedPrice(),
                    'price_cents' => $quote->agreed_price_cents,
                ]],
                'subtotal' => $quote->formattedPrice(),
            ]],
            'conversation_id' => $request->session()->get(AssistantRequest::CONVERSATION_SESSION_KEY),
        ]);
    }

    /**
     * Continue the buyer's own conversation for this shop, or start a fresh one.
     */
    private function bindConversation(AssistantRequest $request, StorefrontAssistant $agent, ?User $user): void
    {
        $conversationId = $request->validated('conversation_id');

        if ($user !== null) {
            if ($conversationId !== null) {
                $agent->continue($conversationId, $user);

                return;
            }

            $agent->forUser($user);

            return;
        }

        // Guests: continuity is bound to the Laravel session, not a persisted identity.
        $sessionId = $request->session()->get(AssistantRequest::CONVERSATION_SESSION_KEY);
        $effectiveId = $conversationId ?? $sessionId;

        if ($effectiveId !== null) {
            $agent->continue((string) $effectiveId);

            return;
        }

        $agent->continue(
            app(ConversationStore::class)->storeConversation(
                null,
                null,
                Str::limit($request->validated('message'), 50, preserveWords: true),
            ),
        );
    }

    /**
     * Get the buyer's session-confirmed maximum for the focused product.
     */
    private function confirmedCeilingCents(
        Request $request,
        ?Product $product,
        ?User $user,
        ?string $guestToken,
    ): ?int {
        if ($product === null || ($user === null && $guestToken === null)) {
            return null;
        }

        $buyerKey = $this->authorizationBuyerKey($user, $guestToken);
        $confirmations = $request->session()->get(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY, []);
        $authorization = is_array($confirmations)
            ? ($confirmations[$buyerKey][$product->sku] ?? null)
            : null;

        if (! is_array($authorization) || (int) ($authorization['product_id'] ?? 0) !== $product->id) {
            return null;
        }

        $ceiling = $authorization['ceiling_price_cents'] ?? null;

        return is_numeric($ceiling) && (int) $ceiling > 0 ? (int) $ceiling : null;
    }

    /**
     * Return a session-safe namespace for one authenticated buyer or guest token.
     */
    private function authorizationBuyerKey(?User $user, ?string $guestToken): string
    {
        return $user !== null ? 'user-'.$user->id : 'guest-'.$guestToken;
    }

    /**
     * Collect the public summaries of the products the assistant surfaced this turn.
     *
     * @return list<array<string, mixed>>
     */
    private function surfacedProducts(Merchant $merchant, ?Product $focused, AgentResponse $response): array
    {
        $summaries = [];

        if ($focused !== null) {
            $summaries[$focused->sku] = $this->publicProductSummary($focused);
        }

        foreach ($response->toolResults as $toolResult) {
            if (! $toolResult instanceof ToolResult || ! is_string($toolResult->result)) {
                continue;
            }

            $payload = json_decode($toolResult->result, true);

            if (! is_array($payload)) {
                continue;
            }

            if ($toolResult->name === class_basename(SearchStoreProducts::class)) {
                foreach ($payload['products'] ?? [] as $summary) {
                    if (is_array($summary) && isset($summary['sku'])) {
                        $summaries[$summary['sku']] ??= $summary;
                    }
                }
            }

            if ($toolResult->name === class_basename(GetStoreProduct::class)) {
                $summary = $payload['product'] ?? null;

                if (is_array($summary) && isset($summary['sku'])) {
                    $summaries[$summary['sku']] ??= $summary;
                }
            }

            if ($toolResult->name === class_basename(NegotiateStorePrice::class)) {
                foreach ($payload['alternatives'] ?? [] as $alternative) {
                    if (! is_array($alternative) || ! isset($alternative['sku'])) {
                        continue;
                    }

                    $product = $merchant->products()
                        ->inStock()
                        ->where('sku', (string) $alternative['sku'])
                        ->first();

                    if ($product !== null) {
                        $summaries[$product->sku] ??= $this->publicProductSummary($product);
                    }
                }
            }
        }

        return array_values($summaries);
    }

    /**
     * Turn the assistant's add-to-cart proposals into buyer-confirmation actions.
     *
     * @return list<array<string, mixed>>
     */
    private function cartProposals(Request $request, AgentResponse $response): array
    {
        $items = [];
        $proposalFailed = false;
        $seenSkus = [];
        $proposals = $request->session()->get(AssistantRequest::CART_PROPOSALS_SESSION_KEY, []);
        $proposals = is_array($proposals) ? $proposals : [];

        foreach ($response->toolResults as $toolResult) {
            if (! $toolResult instanceof ToolResult || ! is_string($toolResult->result)) {
                continue;
            }

            if ($toolResult->name !== class_basename(ProposeAddToCart::class)) {
                continue;
            }

            $payload = json_decode($toolResult->result, true);
            if (! is_array($payload) || ($payload['status'] ?? null) !== 'proposed') {
                $proposalFailed = true;

                continue;
            }

            $action = $payload['action'] ?? null;

            if (! is_array($action)) {
                $proposalFailed = true;

                continue;
            }

            $actionItems = isset($action['items']) && is_array($action['items'])
                ? $action['items']
                : (isset($action['sku']) ? [$action] : []);

            foreach ($actionItems as $actionItem) {
                if (! is_array($actionItem) || ! isset($actionItem['sku'])) {
                    $proposalFailed = true;

                    continue;
                }

                if (isset($seenSkus[$actionItem['sku']])) {
                    $proposalFailed = true;

                    continue;
                }

                $seenSkus[$actionItem['sku']] = true;

                $items[] = [
                    'sku' => $actionItem['sku'],
                    'quantity' => $actionItem['quantity'] ?? 1,
                    'quote_id' => $actionItem['quote_id'] ?? null,
                    'name' => $actionItem['name'] ?? null,
                    'price' => $actionItem['price'] ?? null,
                    'price_cents' => $actionItem['price_cents'] ?? 0,
                ];
            }
        }

        if ($proposalFailed) {
            return [];
        }

        if ($items !== []) {
            $actionId = (string) Str::ulid();
            $proposals[$actionId] = [
                'items' => array_map(static fn (array $item): array => [
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'quote_id' => $item['quote_id'],
                ], $items),
            ];
            $request->session()->put(AssistantRequest::CART_PROPOSALS_SESSION_KEY, $proposals);

            return [[
                'type' => 'confirm_add_to_cart',
                'action_id' => $actionId,
                'items' => $items,
                'subtotal' => number_format(array_sum(array_map(
                    static fn (array $item): int => (int) $item['price_cents'] * (int) $item['quantity'],
                    $items,
                )) / 100, 2, '.', ''),
                'sku' => $items[0]['sku'],
                'quantity' => $items[0]['quantity'],
                'name' => $items[0]['name'],
                'price' => $items[0]['price'],
            ]];
        }

        return [];
    }

    /**
     * Re-resolve the proposal's quote as still owned, valid and priced.
     */
    private function resolveConfirmedQuote(
        AssistantRequest $request,
        Product $product,
        ?string $quoteId,
        bool $lockForUpdate = false,
    ): ?Quote {
        if ($quoteId === null) {
            return null;
        }

        $query = $this->buyers->scope(Quote::query(), $request)
            ->where('quote_id', $quoteId)
            ->where('product_id', $product->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $quote = $query->first();

        if ($quote === null || ! $quote->status->isAcceptable() || $quote->isExpired()) {
            return null;
        }

        return $quote->agreed_price_cents === null ? null : $quote;
    }

    /**
     * Get the buyer's newest still-valid pending quote for the focused product.
     */
    private function latestPendingQuote(Request $request, ?Product $product): ?Quote
    {
        if ($product === null) {
            return null;
        }

        return $this->buyers->scope(Quote::query(), $request)
            ->where('product_id', $product->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteSummary(Quote $quote): array
    {
        return [
            'id' => $quote->quote_id,
            'price' => $quote->formattedPrice(),
            'expiresAt' => $quote->expires_at?->toIso8601String(),
        ];
    }

    /**
     * The pending buyer-confirmation actions derived from the held quote and any
     * cart proposals the assistant prepared this turn.
     *
     * @param  list<array<string, mixed>>  $cartActions
     * @return list<array<string, mixed>>
     */
    private function actions(?Quote $quote, ?Product $product, array $cartActions = []): array
    {
        $actions = [];

        if ($quote !== null && $product !== null) {
            $actions[] = [
                'type' => 'confirm_quote',
                'quote_id' => $quote->quote_id,
                'product_sku' => $product->sku,
                'price' => $quote->formattedPrice(),
                'expires_at' => $quote->expires_at?->toIso8601String(),
            ];
        }

        return array_merge($actions, $cartActions);
    }
}
