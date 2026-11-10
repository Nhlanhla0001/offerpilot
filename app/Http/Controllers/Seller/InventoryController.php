<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * List everything the merchant sells.
     */
    public function index(Request $request): Response
    {
        $merchant = $request->user()->merchant;

        if ($merchant === null) {
            return Inertia::render('inventory/setup');
        }

        $products = $merchant->products()
            ->with('rules')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type,
                'brand' => $product->brand,
                'category' => $product->category,
                'image' => $product->imageUrl(),
                'list_price' => $this->money($product->list_price_cents),
                'floor_price' => $this->money($product->rules->floor_price_cents ?? 0),
                'stock' => $product->stock_quantity,
                'availability' => $product->availabilityLabel(),
                'negotiation' => $product->rules !== null,
            ]);

        return Inertia::render('inventory/index', [
            'merchant' => ['name' => $merchant->name],
            'products' => $products,
        ]);
    }

    /**
     * Show the form for a new product.
     */
    public function create(Request $request): Response
    {
        abort_if($request->user()->merchant === null, 403);

        return Inertia::render('inventory/form', [
            'product' => null,
        ]);
    }

    /**
     * Create the shop a new seller will manage.
     */
    public function storeSetup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:merchants,email'],
        ]);

        $request->user()->merchant()->create([
            'name' => $validated['name'],
            'tagline' => $validated['tagline'] ?? null,
            'email' => $validated['email'],
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Your shop is ready. Add your first product.');
    }

    /**
     * Store a new product with its private rules.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $merchant = $request->user()->merchant;

        $product = $merchant->products()->create($this->attributes($request));

        $product->rules()->create($this->rules($request));

        return redirect()
            ->route('inventory.index')
            ->with('success', "{$product->name} was added to your shop.");
    }

    /**
     * Show the form for an existing product.
     */
    public function edit(Request $request, Product $product): Response
    {
        abort_unless($product->merchant_id === $request->user()->merchant?->id, 404);

        $product->load('rules');

        return Inertia::render('inventory/form', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'category' => $product->category,
                'type' => $product->type,
                'description' => $product->description,
                'list_price' => number_format($product->list_price_cents / 100, 2, '.', ''),
                'floor_price' => number_format(($product->rules->floor_price_cents ?? 0) / 100, 2, '.', ''),
                'stock_quantity' => $product->stock_quantity,
                'image_path' => $product->image_path,
                'negotiation_enabled' => $product->rules !== null,
                'max_rounds' => $product->rules->max_rounds ?? 3,
            ],
        ]);
    }

    /**
     * Update an existing product and its rules.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        abort_unless($product->merchant_id === $request->user()->merchant?->id, 404);

        $product->update($this->attributes($request));

        $product->rules()->updateOrCreate(
            ['product_id' => $product->id],
            $this->rules($request),
        );

        return redirect()
            ->route('inventory.index')
            ->with('success', "{$product->name} was updated.");
    }

    /**
     * Map validated input onto product columns.
     *
     * @return array<string, mixed>
     */
    private function attributes(ProductRequest $request): array
    {
        $validated = $request->validated();

        $attributes = [
            'name' => $validated['name'],
            'sku' => strtoupper($validated['sku']),
            'brand' => $validated['brand'] ?? null,
            'category' => $validated['category'] ?? null,
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'image_path' => $validated['image_path'] ?? null,
            'list_price_cents' => (int) round(((float) $validated['list_price']) * 100),
            'stock_quantity' => (int) $validated['stock_quantity'],
        ];

        if ($request->hasFile('image')) {
            $existing = $request->route('product')?->image_path;

            $attributes['image_path'] = $request->file('image')->store('products', 'public');

            if (is_string($existing) && ! str_starts_with($existing, 'demo/')) {
                Storage::disk('public')->delete($existing);
            }
        }

        return $attributes;
    }

    /**
     * Map validated input onto merchant rule columns.
     *
     * @return array<string, mixed>
     */
    private function rules(ProductRequest $request): array
    {
        $validated = $request->validated();

        return [
            'floor_price_cents' => (int) round(((float) $validated['floor_price']) * 100),
            'max_rounds' => (int) $validated['max_rounds'],
            'allow_alternatives' => $request->boolean('negotiation_enabled'),
        ];
    }

    /**
     * Format minor units for the browser.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Build a unique shop slug from a shop name.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        $suffix = 2;

        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
