<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

/**
 * Owns cart state and, more importantly, cart pricing.
 *
 * Prices are never trusted from the browser: every line is resolved here from the
 * product record or a still-valid quote, and stock is checked before a line moves.
 */
final class CartService
{
    /**
     * Flat shipping charged when a cart contains a physical item, in minor units.
     */
    public const FLAT_SHIPPING_CENTS = 1500;

    /**
     * The most units of one product a buyer may add.
     */
    public const MAX_QUANTITY = 10;

    /**
     * Get or create the cart owned by this session.
     */
    public function forSession(string $sessionId, ?int $userId = null): Cart
    {
        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $userId],
        );
    }

    /**
     * Add a product to the cart, or increase its quantity.
     */
    public function add(Cart $cart, Product $product, int $quantity = 1, ?Quote $quote = null): CartItem
    {
        $quantity = max(1, min($quantity, $this->ceilingFor($product)));

        $existing = $cart->items()->where('product_id', $product->id)->first();

        if ($existing instanceof CartItem) {
            return $this->setQuantity($existing, $existing->quantity + $quantity);
        }

        return $cart->items()->create([
            'product_id' => $product->id,
            'quote_id' => $this->validQuote($quote, $product)?->id,
            'quantity' => $quantity,
            'unit_price_cents' => $this->validQuote($quote, $product)->agreed_price_cents
                ?? $product->list_price_cents,
        ])->refresh();
    }

    /**
     * Set an absolute quantity on a line, clamped to what is actually available.
     */
    public function setQuantity(CartItem $item, int $quantity): CartItem
    {
        $quantity = max(1, min($quantity, $this->ceilingFor($item->product)));

        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    /**
     * Remove a line from the cart.
     */
    public function remove(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Resolve the cart into buyer-facing lines and authoritative totals.
     *
     * @return array<string, mixed>
     */
    public function summary(Cart $cart): array
    {
        $items = $cart->items()->with(['product', 'quote'])->orderBy('id')->get();

        $lines = [];
        $subtotal = 0;
        $hasPhysical = false;

        foreach ($items as $item) {
            $product = $item->product;

            $quote = $this->validQuote($item->quote, $product);
            $unitPrice = $quote->agreed_price_cents ?? $product->list_price_cents;
            $lineTotal = $unitPrice * $item->quantity;
            $subtotal += $lineTotal;

            if (! $product->isService()) {
                $hasPhysical = true;
            }

            $lines[] = [
                'id' => $item->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'image' => $product->imageUrl(),
                'quantity' => $item->quantity,
                'unit_price' => $this->money($unitPrice),
                'line_total' => $this->money($lineTotal),
                'negotiated' => $quote !== null,
                'max_quantity' => $this->ceilingFor($product),
            ];
        }

        $shipping = $hasPhysical ? self::FLAT_SHIPPING_CENTS : 0;
        $total = $subtotal + $shipping;

        return [
            'items' => $lines,
            'count' => array_sum(array_column($lines, 'quantity')),
            'subtotal' => $this->money($subtotal),
            'shipping' => $this->money($shipping),
            'total' => $this->money($total),
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents' => $total,
            'currency' => 'USD',
            'has_physical' => $hasPhysical,
        ];
    }

    /**
     * Clear every line from a cart, used once an order is created.
     */
    public function clear(Cart $cart): void
    {
        DB::transaction(fn () => $cart->items()->delete());
    }

    /**
     * Determine the maximum quantity a buyer may hold of a product.
     */
    public function ceilingFor(?Product $product): int
    {
        if ($product === null) {
            return 1;
        }

        if ($product->isService()) {
            return self::MAX_QUANTITY;
        }

        return max(1, min(self::MAX_QUANTITY, $product->stock_quantity));
    }

    /**
     * Return the quote only if it still authorises this product for this buyer.
     */
    private function validQuote(?Quote $quote, Product $product): ?Quote
    {
        if (! $quote instanceof Quote) {
            return null;
        }

        if ($quote->product_id !== $product->id) {
            return null;
        }

        if (! $quote->status->isAcceptable() || $quote->isExpired()) {
            return null;
        }

        return $quote->agreed_price_cents === null ? null : $quote;
    }

    /**
     * Format minor units for the browser.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
