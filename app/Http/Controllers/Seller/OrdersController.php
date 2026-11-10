<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrdersController extends Controller
{
    /**
     * List the merchant's orders. Only verified captures count as sales.
     */
    public function index(Request $request): Response
    {
        $merchant = $request->user()->merchant;

        abort_if($merchant === null, 403);

        $orders = Order::query()
            ->where('merchant_id', $merchant->id)
            ->withCount('items')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Order $order): array => [
                'order_number' => $order->order_number,
                'customer' => $order->customer_name,
                'email' => $order->customer_email,
                'items' => $order->items_count,
                'total' => $order->formattedAmount(),
                'status' => $order->status->value,
                'paid' => $order->status === OrderStatus::Paid,
                'capture_id' => $order->paypal_capture_id,
                'placed_at' => $order->created_at?->format('d M Y, H:i'),
            ]);

        $paid = Order::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', OrderStatus::Paid);

        return Inertia::render('orders/index', [
            'merchantName' => $merchant->name,
            'orders' => $orders,
            'summary' => [
                'paid_count' => (clone $paid)->count(),
                'revenue' => number_format((int) (clone $paid)->sum('amount_cents') / 100, 2, '.', ','),
                'inventory_count' => $merchant->products()->count(),
                'low_stock' => $merchant->products()->where('type', 'physical')->where('stock_quantity', '<=', 1)->count(),
            ],
        ]);
    }
}
