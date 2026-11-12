<?php

namespace App\Ai\Tools;

use App\Ai\PresentsPublicProducts;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Search the selected merchant's available catalogue.
 *
 * The result only ever contains public product summaries. The merchant's cost
 * and private pricing rules are not queried, returned, or described.
 */
class SearchStoreProducts implements Tool
{
    use PresentsPublicProducts;

    public function __construct(private readonly Merchant $merchant)
    {
        //
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search this shop\'s available catalogue by name, brand, category or description '
            .'and return public product summaries. Never reveals pricing rules, cost, or any '
            .'merchant-private field.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:200'],
        ]);

        $term = '%'.trim((string) $validated['query']).'%';

        $products = $this->merchant->products()
            ->inStock()
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('category', 'like', $term)
                    ->orWhere('description', 'like', $term);
            })
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => $this->publicProductSummary($product))
            ->all();

        return json_encode(['products' => $products], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->min(1)
                ->max(200)
                ->description('Text to match against product name, brand, category or description.')
                ->required(),
        ];
    }
}
