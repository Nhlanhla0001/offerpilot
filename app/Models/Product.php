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
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property string $type
 * @property string|null $brand
 * @property string|null $category
 * @property string $sku
 * @property string|null $description
 * @property array<int, string>|null $features
 * @property array<string, string>|null $specifications
 * @property string|null $image_path
 * @property int $list_price_cents
 * @property int|null $unit_cost_cents
 * @property int $stock_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant $merchant
 * @property-read MerchantRule|null $rules
 */
#[Fillable([
    'merchant_id',
    'name',
    'type',
    'brand',
    'category',
    'sku',
    'description',
    'features',
    'specifications',
    'image_path',
    'list_price_cents',
    'unit_cost_cents',
    'stock_quantity',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'specifications' => 'array',
        ];
    }

    /**
     * The fallback artwork used when a product has no image.
     */
    private const FALLBACK_IMAGE = 'demo/accessories.svg';

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
        $query->where(function (Builder $query): void {
            $query->where('type', 'service')
                ->orWhere('stock_quantity', '>', 0);
        });
    }

    /**
     * Determine whether this item is a service rather than a physical good.
     */
    public function isService(): bool
    {
        return $this->type === 'service';
    }

    /**
     * Determine whether the item can be bought right now.
     */
    public function isAvailable(): bool
    {
        return $this->isService() || $this->stock_quantity > 0;
    }

    /**
     * Get a display label for availability.
     */
    public function availabilityLabel(): string
    {
        if ($this->isService()) {
            return $this->isAvailable() ? 'Available' : 'Unavailable';
        }

        return $this->stock_quantity > 0
            ? $this->stock_quantity.' in stock'
            : 'Out of stock';
    }

    /**
     * Get a publicly usable image URL for this product.
     */
    public function imageUrl(): string
    {
        if ($this->image_path === null) {
            return asset(self::FALLBACK_IMAGE);
        }

        if (str_starts_with($this->image_path, 'demo/')) {
            return asset($this->image_path);
        }

        return Storage::disk('public')->url($this->image_path);
    }
}
