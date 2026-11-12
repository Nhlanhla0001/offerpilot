<?php

namespace App\Ai\Tools;

use App\Models\Cart;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Prepare a buyer's add-to-cart action.
 *
 * This tool proposes an action and deliberately changes nothing: the browser
 * asks the buyer to confirm before the cart is touched. A confirmed request is
 * handled separately and re-resolves the product, quote and quantity server-side
 * before calling CartService. No PayPal order or capture access exists here.
 */
class ProposeAddToCart implements Tool
{
    public function __construct(
        private readonly Merchant $merchant,
        private readonly ?User $user,
        private readonly ?string $guestToken = null,
        private readonly ?Cart $cart = null,
    ) {
        //
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Prepare an add-to-cart action for the buyer. It proposes adding a product to the '
            .'cart but does not change the cart; the buyer must confirm before anything is added. '
            .'For one product, provide its SKU, optional quantity, and optional quote_id from a previous '
            .'negotiation. For several products requested together, provide an items array with each SKU, '
            .'quantity, and optional quote_id so the buyer can confirm the whole batch once.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'sku' => ['required_without:items', 'nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'quote_id' => ['nullable', 'string', 'max:64'],
            'items' => ['required_without:sku', 'nullable', 'array', 'min:2', 'max:10'],
            'items.*.sku' => ['required_with:items', 'string', 'max:100', 'distinct'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'items.*.quote_id' => ['nullable', 'string', 'max:64'],
        ]);

        $requestedItems = $validated['items'] ?? [[
            'sku' => $validated['sku'],
            'quantity' => $validated['quantity'] ?? null,
            'quote_id' => $validated['quote_id'] ?? null,
        ]];
        $actions = [];

        foreach ($requestedItems as $requestedItem) {
            $product = $this->merchant->products()
                ->inStock()
                ->where('sku', (string) $requestedItem['sku'])
                ->first();

            if ($product === null) {
                return json_encode([
                    'status' => 'unavailable',
                    'message' => 'One or more items are not available right now. No cart action was prepared.',
                ], JSON_THROW_ON_ERROR);
            }

            $quoteId = isset($requestedItem['quote_id']) ? (string) $requestedItem['quote_id'] : null;
            $quote = $this->validQuote(
                $product,
                $quoteId,
            );

            if ($quoteId !== null && $quote === null) {
                return json_encode([
                    'status' => 'unavailable',
                    'message' => 'The agreed price is no longer available. No cart action was prepared.',
                ], JSON_THROW_ON_ERROR);
            }

            $cartItem = $this->cart?->items()->where('product_id', $product->id)->first();
            $maximumQuantity = $cartItem?->quote_id !== null
                ? 0
                : app(CartService::class)->ceilingFor($product) - ($cartItem?->quantity ?? 0);

            if ($maximumQuantity < 1 || ($quote !== null && $cartItem !== null)) {
                return json_encode([
                    'status' => 'unavailable',
                    'message' => 'This item cannot be added in the requested quantity at its current cart price. No cart action was prepared.',
                ], JSON_THROW_ON_ERROR);
            }

            $quantity = $quote !== null
                ? 1
                : max(1, min((int) ($requestedItem['quantity'] ?? 1), $maximumQuantity));
            $unitPriceCents = $quote?->agreed_price_cents ?? $product->list_price_cents;

            $actions[] = [
                'type' => 'add_to_cart',
                'sku' => $product->sku,
                'quantity' => $quantity,
                'quote_id' => $quote?->quote_id,
                'name' => $product->name,
                'price_cents' => $unitPriceCents,
                'price' => number_format($unitPriceCents / 100, 2, '.', ''),
            ];
        }

        return json_encode([
            'status' => 'proposed',
            'message' => count($actions) === 1
                ? 'Prepared adding '.$actions[0]['name'].' to the cart. Ask the buyer to confirm.'
                : 'Prepared adding '.count($actions).' items to the cart. Ask the buyer to confirm the batch.',
            'action' => count($actions) === 1
                ? $actions[0]
                : ['type' => 'add_to_cart_batch', 'items' => $actions],
        ], JSON_THROW_ON_ERROR);
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
                ->description('The SKU of one product to add. Use this or items, not both.'),
            'quantity' => $schema->integer()
                ->min(1)
                ->max(10)
                ->description('How many units to add, between 1 and 10.'),
            'quote_id' => $schema->string()
                ->max(64)
                ->description('The quote_id from a previous negotiation to use the negotiated price.'),
            'items' => $schema->array()
                ->min(2)
                ->max(10)
                ->items($schema->object([
                    'sku' => $schema->string()->min(1)->max(100)->required(),
                    'quantity' => $schema->integer()->min(1)->max(10),
                    'quote_id' => $schema->string()->max(64),
                ])->withoutAdditionalProperties())
                ->description('Several different products requested together. The buyer confirms all items as one batch.'),
        ];
    }

    /**
     * Return the quote only if it is still valid and owned by this buyer.
     */
    private function validQuote(Product $product, ?string $quoteId): ?Quote
    {
        if ($quoteId === null) {
            return null;
        }

        $quote = Quote::query()
            ->where($this->ownership())
            ->where('quote_id', $quoteId)
            ->where('product_id', $product->id)
            ->first();

        if ($quote === null || ! $quote->status->isAcceptable() || $quote->isExpired()) {
            return null;
        }

        return $quote->agreed_price_cents === null ? null : $quote;
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
