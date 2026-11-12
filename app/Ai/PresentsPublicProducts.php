<?php

namespace App\Ai;

use App\Models\Product;

/**
 * Shapes a product into the public, buyer-safe summary shared by the storefront
 * assistant tools and the assistant endpoint response.
 *
 * The merchant's cost and private negotiation rules are deliberately absent:
 * this summary is the only catalogue shape the language model ever sees.
 */
trait PresentsPublicProducts
{
    /**
     * @return array<string, mixed>
     */
    protected function publicProductSummary(Product $product): array
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
            'price' => $this->money($product->list_price_cents),
            'price_cents' => $product->list_price_cents,
            'available' => $product->isAvailable(),
            'availability' => $product->availabilityLabel(),
            'image' => $product->imageUrl(),
        ];
    }

    /**
     * Format a minor-unit amount as a plain decimal string.
     */
    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
