<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Notifications\OrderPaid;
use App\Services\CartService;
use App\Services\PayPalService;
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
    ) {
        //
    }

    /**
     * Show the checkout for the current cart.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $summary = $this->cart->summary($this->currentCart($request));

        if ($summary['count'] === 0) {
            return redirect()->route('store.index');
        }

        return Inertia::render('checkout/index', [
            'cart' => $summary,
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

        $order = DB::transaction(function () use ($cart, $summary, $validated): Order {
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
                'paypal_order_id' => 'pending-'.uniqid(),
            ]);

            foreach ($cart->items()->with('product')->get() as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quote_id' => $item->quote_id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->unit_price_cents * $item->quantity,
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
            ->with('items')
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

            return response()->json([
                'message' => 'PayPal did not confirm the expected amount.',
            ], 422);
        }

        DB::transaction(function () use ($order, $capture): void {
            $order->update([
                'status' => OrderStatus::Paid,
                'paypal_capture_id' => $this->paypal->captureId($capture),
            ]);

            foreach ($order->items as $line) {
                if ($line->product_id !== null) {
                    $line->product?->decrement('stock_quantity', $line->quantity);
                }
            }

            Cart::query()->find($order->cart_id)?->items()->delete();

            $order->merchant?->owner?->notify(new OrderPaid($order));
        });

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
