<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $paidCount = (clone $paid)->count();

        $costedPaidOrders = Order::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', OrderStatus::Paid)
            ->whereHas('items')
            ->whereDoesntHave('items', fn (Builder $query) => $query->whereNull('unit_cost_cents'));
        $costedOrderCount = (clone $costedPaidOrders)->count();
        $grossProfit = $costedOrderCount > 0
            ? OrderItem::query()
                ->whereIn('order_id', (clone $costedPaidOrders)->select('id'))
                ->sum(DB::raw('line_total_cents - unit_cost_cents * quantity'))
            : null;

        $offerCount = Quote::query()
            ->whereIn('product_id', $merchant->products()->select('id'))
            ->where('source', 'offer')
            ->count();
        $accepted = Quote::query()
            ->whereIn('product_id', $merchant->products()->select('id'))
            ->where('source', 'offer')
            ->where('status', 'fulfilled')
            ->count();

        $trendFrom = now()->startOfDay()->subDays(6);
        $trendRows = (clone $paid)
            ->where('created_at', '>=', $trendFrom)
            ->selectRaw('DATE(created_at) as day, SUM(amount_cents) as revenue_cents')
            ->groupBy('day')
            ->pluck('revenue_cents', 'day');
        $revenueTrend = collect(range(0, 6))
            ->map(function (int $offset) use ($trendFrom, $trendRows): array {
                $day = $trendFrom->copy()->addDays($offset);
                $date = $day->toDateString();

                return [
                    'label' => $day->format('D'),
                    'date' => $date,
                    'revenue_cents' => (int) ($trendRows[$date] ?? 0),
                ];
            })
            ->all();

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

        return Inertia::render('seller/dashboard', [
            'merchantName' => $merchant->name,
            'stats' => [
                'paid_orders' => $paidCount,
                'revenue' => number_format($revenue / 100, 2, '.', ','),
                'average_order_value' => $paidCount > 0
                    ? number_format($revenue / $paidCount / 100, 2, '.', ',')
                    : null,
                'gross_profit' => $grossProfit === null
                    ? null
                    : number_format((int) $grossProfit / 100, 2, '.', ','),
                'profit_costed_orders' => $costedOrderCount,
                'profit_paid_orders' => $paidCount,
                'offer_acceptance_rate' => $offerCount > 0
                    ? (int) round($accepted / $offerCount * 100)
                    : null,
                'revenue_trend' => $revenueTrend,
                'offers' => $offerCount,
                'accepted' => $accepted,
                'items' => $merchant->products()->count(),
                'low_stock' => $merchant->products()->where('type', 'physical')->where('stock_quantity', '<=', 1)->count(),
                'pending' => $merchant->orders()->where('status', '!=', OrderStatus::Paid)->count(),
            ],
            'recent' => $recent,
        ]);
    }
}
