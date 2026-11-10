<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property string $sku
 * @property string|null $description
 * @property int $list_price_cents
 * @property int $stock_quantity
 * @property string|null $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant $merchant
 * @property-read MerchantRule|null $rules
 */
#[Fillable(['merchant_id', 'name', 'sku', 'description', 'list_price_cents', 'stock_quantity', 'image_url'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the merchant that sells this product.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the private negotiation rules for this product.
     *
     * These values must never be exposed to the buyer or to the language model.
     *
     * @return HasOne<MerchantRule, $this>
     */
    public function rules(): HasOne
    {
        return $this->hasOne(MerchantRule::class);
    }

    /**
     * Scope the query to in-stock products.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeInStock(Builder $query): void
    {
        $query->where('stock_quantity', '>', 0);
    }
}
