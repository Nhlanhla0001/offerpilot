<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CaptureCheckoutRequest;
use App\Http\Requests\Storefront\CreateCheckoutRequest;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Quote;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(private readonly PayPalService $paypal)
    {
        //
    }

    /**
     * Create a PayPal order for a server-issued quote.
     */
    public function create(CreateCheckoutRequest $request): JsonResponse
    {
        $quote = Quote::query()
            ->with('product')
            ->where('quote_id', $request->string('quote')->toString())
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $quote->status->isAcceptable() || $quote->isExpired()) {
            return response()->json(['message' => 'This quote is no longer available. Negotiate again.'], 422);
        }

        if ($quote->agreed_price_cents === null) {
            return response()->json(['message' => 'This quote has no agreed price.'], 422);
        }

        $paypalOrder = $this->paypal->createOrder(
            $quote->agreed_price_cents,
            $quote->quote_id,
            $quote->product->name,
        );

        $order = Order::create([
            'quote_id' => $quote->id,
            'paypal_order_id' => (string) ($paypalOrder['id'] ?? ''),
            'amount_cents' => $quote->agreed_price_cents,
            'currency' => (string) config('services.paypal.currency', 'USD'),
            'status' => OrderStatus::Created,
        ]);

        return response()->json([
            'paypalOrderId' => $order->paypal_order_id,
            'amount' => $order->formattedAmount(),
        ]);
    }

    /**
     * Capture a PayPal order and verify the payment server-side.
     */
    public function capture(CaptureCheckoutRequest $request): JsonResponse
    {
        $order = Order::query()
            ->with('quote')
            ->where('paypal_order_id', $request->string('paypal_order_id')->toString())
            ->firstOrFail();

        abort_unless($order->quote->user_id === $request->user()->id, 403);

        if ($order->status === OrderStatus::Paid) {
            return response()->json([
                'status' => 'paid',
                'captureId' => $order->paypal_capture_id,
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
            'error_message' => $verified ? null : 'Capture did not match the quoted amount.',
        ]);

        if (! $verified) {
            $order->update(['status' => OrderStatus::Failed]);

            return response()->json([
                'message' => 'PayPal did not confirm a completed payment for the quoted amount.',
            ], 422);
        }

        DB::transaction(function () use ($order, $capture): void {
            $order->update([
                'status' => OrderStatus::Paid,
                'paypal_capture_id' => $this->paypal->captureId($capture),
            ]);

            $order->quote->update(['status' => 'fulfilled']);

            $order->quote->product->decrement('stock_quantity');
        });

        return response()->json([
            'status' => 'paid',
            'captureId' => $order->paypal_capture_id,
            'amount' => $order->formattedAmount(),
        ]);
    }
}
