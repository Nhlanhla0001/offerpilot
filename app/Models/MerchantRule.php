<?php

namespace App\Models;

use Database\Factories\MerchantRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Private merchant guardrails for a single product.
 *
 * This model is the only place a floor price lives. It must never be serialized
 * into an Inertia response, a language model prompt, or a log entry.
 *
 * @property int $id
 * @property int $product_id
 * @property int $floor_price_cents
 * @property int $max_rounds
 * @property bool $allow_alternatives
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
#[Fillable(['product_id', 'floor_price_cents', 'max_rounds', 'allow_alternatives'])]
class MerchantRule extends Model
{
    /** @use HasFactory<MerchantRuleFactory> */
    use HasFactory;

    /**
     * Get the product these rules belong to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_alternatives' => 'boolean',
        ];
    }
}
