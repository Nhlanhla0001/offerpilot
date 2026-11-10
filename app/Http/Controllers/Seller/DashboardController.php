<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show how the shop is performing and what needs attention.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $merchant = $request->user()->merchant;

        // A shopper has no shop to manage. Send them back to the storefront,
        // where opening a shop is an explicit, separate decision.
        if ($merchant === null) {
            return redirect()->route('store.index');
        }

        $paid = $merchant->orders()->where('status', OrderStatus::Paid);

        $revenue = (clone $paid)->sum('amount_cents');

        $recent = $merchant->orders()
            ->withCount('items')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($order): array => [
                'order_number' => $order->order_number,
                'customer' => $order->customer_name,
                'items' => $order->items_count,
                'total' => $order->formattedAmount(),
                'paid' => $order->status === OrderStatus::Paid,
                'placed_at' => $order->created_at?->format('d M Y, H:i'),
            ]);

        $offers = Quote::query()
            ->whereIn('product_id', $merchant->products()->select('id'))
            ->where('source', 'offer')
            ->count();

        $accepted = Quote::query()
            ->whereIn('product_id', $merchant->products()->select('id'))
            ->where('source', 'offer')
            ->where('status', 'fulfilled')
            ->count();

        return Inertia::render('seller/dashboard', [
            'merchantName' => $merchant->name,
            'stats' => [
                'paid_orders' => (clone $paid)->count(),
                'revenue' => number_format($revenue / 100, 2, '.', ','),
                'offers' => $offers,
                'accepted' => $accepted,
                'items' => $merchant->products()->count(),
                'low_stock' => $merchant->products()->where('type', 'physical')->where('stock_quantity', '<=', 1)->count(),
                'pending' => $merchant->orders()->where('status', '!=', OrderStatus::Paid)->count(),
            ],
            'recent' => $recent,
        ]);
    }
}
