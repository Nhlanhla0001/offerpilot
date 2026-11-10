<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Show the products open to offers.
     */
    public function index(): Response
    {
        $products = Product::query()
            ->inStock()
            ->with('merchant')
            ->orderBy('list_price_cents')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => [
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $this->price($product->list_price_cents),
                'merchant' => $product->merchant->name,
            ]);

        return Inertia::render('store/index', [
            'products' => $products,
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
            'product' => [
                'sku' => $product->sku,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $this->price($product->list_price_cents),
                'priceCents' => $product->list_price_cents,
                'stock' => $product->stock_quantity,
                'merchant' => $product->merchant->name,
            ],
        ]);
    }

    /**
     * Format a minor-unit amount for display.
     */
    private function price(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
