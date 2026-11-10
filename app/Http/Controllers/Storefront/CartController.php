<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Quote;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart)
    {
        //
    }

    /**
     * Show the current buyer's cart.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->cart->summary($this->currentCart($request)));
    }

    /**
     * Add a product to the cart, optionally at an authorised quote price.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'quote' => ['nullable', 'string'],
        ]);

        $product = Product::query()->where('sku', $validated['sku'])->firstOrFail();

        if (! $product->isAvailable()) {
            return response()->json(['message' => 'This item is not available right now.'], 422);
        }

        $quote = null;

        if (isset($validated['quote'])) {
            $quote = Quote::query()
                ->where('quote_id', $validated['quote'])
                ->where('product_id', $product->id)
                ->where('user_id', $request->user()?->id)
                ->first();
        }

        $cart = $this->currentCart($request);

        $this->cart->add($cart, $product, (int) ($validated['quantity'] ?? 1), $quote);

        return response()->json($this->cart->summary($cart->refresh()));
    }

    /**
     * Change the quantity of a cart line.
     */
    public function update(Request $request, CartItem $item): JsonResponse
    {
        $this->authoriseItem($request, $item);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $this->cart->setQuantity($item, (int) $validated['quantity']);

        return response()->json($this->cart->summary($this->currentCart($request)));
    }

    /**
     * Remove a line from the cart.
     */
    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        $this->authoriseItem($request, $item);

        $this->cart->remove($item);

        return response()->json($this->cart->summary($this->currentCart($request)));
    }

    /**
     * Resolve the cart for this session, creating it on first use.
     */
    private function currentCart(Request $request): Cart
    {
        return $this->cart->forSession($request->session()->getId(), $request->user()?->id);
    }

    /**
     * Ensure the line belongs to the current buyer's cart.
     */
    private function authoriseItem(Request $request, CartItem $item): void
    {
        abort_unless(
            $item->cart_id === $this->currentCart($request)->id,
            404,
        );
    }
}
