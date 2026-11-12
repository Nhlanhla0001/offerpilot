<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen snapshot of what an order contained, so history survives edits.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $quote_id
 * @property string $name
 * @property string $sku
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int|null $unit_cost_cents
 * @property int $line_total_cents
 */
#[Fillable([
    'order_id',
    'product_id',
    'quote_id',
    'name',
    'sku',
    'quantity',
    'unit_price_cents',
    'unit_cost_cents',
    'line_total_cents',
])]
class OrderItem extends Model
{
    /**
     * Get the order this line belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product this line was bought from, if it still exists.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the negotiated quote backing this line, when applicable.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
