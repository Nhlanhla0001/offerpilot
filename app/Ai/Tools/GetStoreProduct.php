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
 * Get the public facts for one product in the selected merchant's catalogue.
 *
 * The result only ever contains a public product summary, and an unknown SKU is
 * reported as unknown rather than guessed at.
 */
class GetStoreProduct implements Tool
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
        return 'Get the public facts for one product in this shop by its SKU. '
            .'Returns public fields only and states clearly when no product matches the SKU.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
        ]);

        $product = $this->merchant->products()
            ->inStock()
            ->where('sku', (string) $validated['sku'])
            ->first();

        if ($product === null) {
            return json_encode([
                'product' => null,
                'message' => 'This product is not available in this shop.',
            ], JSON_THROW_ON_ERROR);
        }

        return json_encode(['product' => $this->publicProductSummary($product)], JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sku' => $schema->string()
                ->min(1)
                ->max(100)
                ->description('The product SKU to look up.')
                ->required(),
        ];
    }
}
