<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A server-issued price quote. The final price is decided by NegotiationService,
 * never by the language model.
 *
 * @property int $id
 * @property string $quote_id
 * @property int $product_id
 * @property int|null $user_id
 * @property int $target_price_cents
 * @property int $ceiling_price_cents
 * @property int|null $agreed_price_cents
 * @property int $rounds_used
 * @property QuoteStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Order|null $order
 */
#[Fillable([
    'product_id',
    'user_id',
    'target_price_cents',
    'ceiling_price_cents',
    'agreed_price_cents',
    'rounds_used',
    'status',
    'expires_at',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Quote $quote) {
            if (empty($quote->quote_id)) {
                $quote->quote_id = (string) Str::ulid();
            }
        });
    }

    /**
     * Get the product this quote is for.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the buyer who requested this quote.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order created from this quote.
     *
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Determine whether the quote has passed its expiry time.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get the quoted price formatted for display, for example "220.00".
     */
    public function formattedPrice(): ?string
    {
        return $this->agreed_price_cents === null
            ? null
            : number_format($this->agreed_price_cents / 100, 2, '.', '');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'expires_at' => 'datetime',
        ];
    }
}
