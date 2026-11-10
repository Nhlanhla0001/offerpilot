<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Show the merchant's storefront.
     */
    public function index(Request $request): Response
    {
        $merchant = Merchant::query()->orderBy('id')->first();

        $products = Product::query()
            ->with('merchant')
            ->orderBy('category')
            ->orderBy('list_price_cents')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => $this->present($product));

        $categories = $products->pluck('category')->filter()->unique()->values();

        return Inertia::render('store/index', [
            'merchant' => $merchant === null ? null : [
                'name' => $merchant->name,
                'tagline' => $merchant->tagline,
                'initials' => $this->initials($merchant->name),
            ],
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    /**
     * Show a single product with its negotiation panel.
     */
    public function show(Product $product): Response
    {
        $product->load('merchant');

        return Inertia::render('store/show', [
            'paypalClientId' => config('services.paypal.client_id'),
            'product' => $this->present($product) + [
                'priceCents' => $product->list_price_cents,
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
        return number_format($cents / 100, 2, '.', '');
    }
}
