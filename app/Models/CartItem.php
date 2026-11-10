<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int|null $quote_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property-read Product $product
 * @property-read Quote|null $quote
 */
#[Fillable(['cart_id', 'product_id', 'quote_id', 'quantity', 'unit_price_cents'])]
class CartItem extends Model
{
    /**
     * Get the cart this item belongs to.
     *
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the product being bought.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the negotiated quote backing this line, when there is one.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the line total in minor units.
     */
    public function lineTotalCents(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }
}
