<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Notifications\OrderPaid;
use App\Services\CartService;
use App\Services\PayPalService;
use App\Services\StorefrontExperiment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Multi-item checkout.
 *
 * The browser supplies only the cart it already owns and the customer details.
 * Every amount is recomputed here from product records and valid quotes.
 */
class CommerceCheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly PayPalService $paypal,
        private readonly StorefrontExperiment $experiment,
    ) {
        //
    }

    /**
     * Show the checkout for the current cart.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $cart = $this->currentCart($request);
        $summary = $this->cart->summary($cart);

        if ($summary['count'] === 0) {
            return redirect()->route('store.index');
        }

        $merchantName = $cart->items()
            ->with('product.merchant')
            ->first()
            ?->product
            ?->merchant
            ?->name;

        return Inertia::render('checkout/index', [
            'cart' => $summary,
            'merchantName' => $merchantName,
            'paypalClientId' => config('services.paypal.client_id'),
        ]);
    }

    /**
     * Create the internal order and the matching PayPal order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120'],
            'address' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:24'],
            'country' => ['required', 'string', 'max:80'],
        ]);

        $cart = $this->currentCart($request);
        $summary = $this->cart->summary($cart);

        if ($summary['count'] === 0) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        foreach ($cart->items()->with('product')->get() as $item) {
            if (! $item->product->isService() && $item->quantity > $item->product->stock_quantity) {
                return response()->json([
                    'message' => "Only {$item->product->stock_quantity} of {$item->product->name} are left.",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($cart, $summary, $validated, $request): Order {
            $order = Order::create([
                'merchant_id' => $cart->items()->with('product')->first()?->product?->merchant_id,
                'cart_id' => $cart->id,
                'customer_name' => $validated['name'],
                'customer_email' => $validated['email'],
                'shipping_address' => [
                    'address' => $validated['address'],
                    'city' => $validated['city'],
                    'postal_code' => $validated['postal_code'],
                    'country' => $validated['country'],
                ],
                'shipping_method' => $summary['has_physical'] ? 'flat' : 'collection',
                'subtotal_cents' => $summary['subtotal_cents'],
                'shipping_cents' => $summary['shipping_cents'],
                'amount_cents' => $summary['total_cents'],
                'currency' => $summary['currency'],
                'status' => OrderStatus::Created,
                'experiment_variant' => $this->experiment->currentVariant($request),
                'paypal_order_id' => 'pending-'.uniqid(),
            ]);

            $summaryItems = collect($summary['items'])->keyBy('id');

            foreach ($cart->items()->with('product')->get() as $item) {
                $summaryItem = $summaryItems->get($item->id);

                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quote_id' => $item->quote_id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $summaryItem['unit_price_cents'],
                    'unit_cost_cents' => $item->product->unit_cost_cents,
                    'line_total_cents' => $summaryItem['line_total_cents'],
                ]);
            }

            return $order;
        });

        $paypalOrder = $this->paypal->createOrder(
            $order->amount_cents,
            $order->order_number,
            'OfferPilot order',
        );

        $order->update(['paypal_order_id' => (string) ($paypalOrder['id'] ?? '')]);

        $this->experiment->record($request, 'checkout_start', [
            'has_physical' => $summary['has_physical'],
        ]);

        return response()->json([
            'paypalOrderId' => $order->paypal_order_id,
            'orderNumber' => $order->order_number,
            'amount' => $order->formattedAmount(),
        ]);
    }

    /**
     * Capture and verify the payment, then settle the order.
     */
    public function capture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paypal_order_id' => ['required', 'string', 'max:64'],
        ]);

        $order = Order::query()
            ->with(['items.quote', 'items.product'])
            ->where('paypal_order_id', $validated['paypal_order_id'])
            ->firstOrFail();

        abort_unless($order->cart_id === $this->currentCart($request)->id, 403);

        if ($order->status === OrderStatus::Paid) {
            return response()->json([
                'status' => 'paid',
                'orderNumber' => $order->order_number,
                'amount' => $order->formattedAmount(),
            ]);
        }

        $result = DB::transaction(function () use ($order): array {
            $products = Product::query()
                ->whereIn('id', $order->items->pluck('product_id')->filter()->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
                $product = $products->get($item->product_id);

                if ($product !== null && ! $product->isService() && $item->quantity > $product->stock_quantity) {
                    return [
                        'status' => 'stock_unavailable',
                        'message' => "Only {$product->stock_quantity} of {$item->name} are left. Your payment was not captured.",
                    ];
                }
            }

            $capture = $this->paypal->captureOrder($order->paypal_order_id);
            $verified = $this->paypal->captureIsVerified($capture, $order->amount_cents);

            PaymentAttempt::create([
                'order_id' => $order->id,
                'action' => PaymentAction::Capture,
                'status' => (string) ($capture['status'] ?? 'UNKNOWN'),
                'payload' => $capture,
                'error_message' => $verified ? null : 'Capture did not match the order amount.',
            ]);

            if (! $verified) {
                $order->update(['status' => OrderStatus::Failed]);

                return ['status' => 'capture_failed'];
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paypal_capture_id' => $this->paypal->captureId($capture),
            ]);

            foreach ($order->items as $line) {
                $product = $products->get($line->product_id);

                if ($product !== null && ! $product->isService()) {
                    $product->decrement('stock_quantity', $line->quantity);
                }

                $line->quote?->update(['status' => 'fulfilled']);
            }

            Cart::query()->find($order->cart_id)?->items()->delete();

            $order->merchant?->owner?->notify(new OrderPaid($order));

            return ['status' => 'paid'];
        });

        if ($result['status'] === 'stock_unavailable') {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        if ($result['status'] === 'capture_failed') {
            return response()->json([
                'message' => 'PayPal did not confirm the expected amount.',
            ], 422);
        }

        $this->experiment->record($request, 'purchase_verified');

        return response()->json([
            'status' => 'paid',
            'orderNumber' => $order->order_number,
            'amount' => $order->formattedAmount(),
        ]);
    }

    /**
     * Resolve the cart for this session.
     */
    private function currentCart(Request $request): Cart
    {
        return $this->cart->forSession($request->session()->getId(), $request->user()?->id);
    }
}
