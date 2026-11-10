<?php

namespace App\Http\Controllers\Seller;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OffersController extends Controller
{
    /**
     * List every negotiation a buyer has had with this shop.
     *
     * The seller sees what the buyer asked for, what the shop held, and what
     * happened. The private minimum is not part of this screen either: it is
     * configuration, not an offer.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $merchant = $request->user()->merchant;

        if ($merchant === null) {
            return redirect()->route('store.index');
        }

        $filter = $request->string('status')->toString();
        $filter = in_array($filter, ['open', 'paid', 'expired'], true) ? $filter : 'all';

        $scope = fn () => Quote::query()
            ->whereIn('product_id', $merchant->products()->select('id'))
            ->where('source', 'offer');

        $counts = [
            'all' => $scope()->count(),
            'open' => $this->applyFilter($scope(), 'open')->count(),
            'paid' => $this->applyFilter($scope(), 'paid')->count(),
            'expired' => $this->applyFilter($scope(), 'expired')->count(),
        ];

        $offers = $this->applyFilter($scope()->with(['product', 'user']), $filter)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Quote $quote): array => [
                'id' => $quote->quote_id,
                'product' => $quote->product->name,
                'sku' => $quote->product->sku,
                'buyer' => $quote->user?->name,
                'offered' => $this->money($quote->target_price_cents),
                'held' => $quote->agreed_price_cents === null
                    ? null
                    : $this->money($quote->agreed_price_cents),
                'rounds' => $quote->rounds_used,
                'status' => $this->label($quote),
                'expires_at' => $quote->expires_at?->format('d M Y, H:i'),
                'placed_at' => $quote->created_at?->format('d M Y, H:i'),
            ]);

        return Inertia::render('offers/index', [
            'merchantName' => $merchant->name,
            'offers' => $offers,
            'counts' => $counts,
            'filters' => ['status' => $filter],
        ]);
    }

    /**
     * Apply a status filter to a quote query.
     *
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    private function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'open' => $query
                ->where('status', QuoteStatus::Pending)
                ->where('expires_at', '>', now()),
            'paid' => $query->where('status', QuoteStatus::Fulfilled),
            'expired' => $query->where(function (Builder $inner): void {
                $inner->where('status', QuoteStatus::Expired)
                    ->orWhere(function (Builder $pending): void {
                        $pending->where('status', QuoteStatus::Pending)
                            ->where('expires_at', '<=', now());
                    });
            }),
            default => $query,
        };
    }

    /**
     * Get the buyer-facing state of the quote.
     */
    private function label(Quote $quote): string
    {
        if ($quote->status === QuoteStatus::Fulfilled) {
            return 'Paid';
        }

        if ($quote->status === QuoteStatus::Expired || $quote->isExpired()) {
            return 'Expired';
        }

        return 'Open';
    }

    /**
     * Format minor units for display.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', ',');
    }
}
