<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Product;
use App\Services\StorefrontExperiment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly StorefrontExperiment $experiment)
    {
        //
    }

    /**
     * Show the merchant's storefront.
     */
    public function index(Request $request): Response
    {
        $this->experiment->assign($request);

        $merchant = Merchant::query()->orderBy('id')->first();
        $search = trim($request->string('q')->toString());
        $category = trim($request->string('category')->toString());
        $hasAssistantResultFilter = $request->query->has('skus');
        $requestedSkus = $request->query('skus');
        $assistantSkus = is_string($requestedSkus)
            ? collect(explode(',', $requestedSkus))
                ->map(fn (string $sku): string => trim($sku))
                ->filter(fn (string $sku): bool => preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $sku) === 1)
                ->unique()
                ->take(5)
                ->values()
                ->all()
            : [];

        $productQuery = $merchant?->products() ?? Product::query()->whereKey([]);

        if ($hasAssistantResultFilter) {
            $productQuery = $assistantSkus === []
                ? $productQuery->whereKey([])
                : $productQuery->whereIn('sku', $assistantSkus)->inStock();
        }

        $products = $productQuery
            ->with('merchant')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';

                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('category', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('list_price_cents')
            ->orderBy('id')
            ->paginate(6)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->present($product));

        $categories = ($merchant?->products() ?? Product::query()->whereKey([]))
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $assistantProducts = $merchant === null
            ? []
            : $merchant->products()
                ->inStock()
                ->get(['sku', 'name'])
                ->map(fn (Product $product): array => [
                    'sku' => $product->sku,
                    'name' => $product->name,
                ])
                ->all();

        return Inertia::render('store/index', [
            'merchant' => $merchant === null ? null : [
                'name' => $merchant->name,
                'tagline' => $merchant->tagline,
                'initials' => $this->initials($merchant->name),
            ],
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'q' => $search,
                'category' => $category,
                'isAssistantResultFilter' => $hasAssistantResultFilter,
            ],
            'assistant' => [
                'merchantName' => $merchant?->name ?? 'OfferPilot',
                'productSku' => null,
                'products' => $assistantProducts,
            ],
        ]);
    }

    /**
     * Show a single product with its negotiation panel.
     */
    public function show(Request $request, Product $product): Response
    {
        $this->experiment->assign($request);

        $product->load('merchant');

        return Inertia::render('store/show', [
            'paypalClientId' => config('services.paypal.client_id'),
            'product' => $this->present($product) + [
                'priceCents' => $product->list_price_cents,
            ],
            'assistant' => [
                'merchantName' => $product->merchant->name,
                'productSku' => $product->sku,
            ],
        ]);
    }

    /**
     * Shape a product for the storefront. Never includes the private rules.
     *
     * @return array<string, mixed>
     */
    private function present(Product $product): array
    {
        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'brand' => $product->brand,
            'category' => $product->category,
            'type' => $product->type,
            'description' => $product->description,
            'features' => $product->features ?? [],
            'specifications' => $product->specifications ?? [],
            'price' => $this->price($product->list_price_cents),
            'image' => $product->imageUrl(),
            'available' => $product->isAvailable(),
            'availability' => $product->availabilityLabel(),
            'merchant' => $product->merchant->name,
        ];
    }

    /**
     * Get initials for a brand mark.
     */
    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }

    /**
     * Format a minor-unit amount for display.
     */
    private function price(int $cents): string
    {
        return number_format($cents / 100, 2, '.', ',');
    }
}
