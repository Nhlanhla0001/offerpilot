<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $quote_id
 * @property string $paypal_order_id
 * @property string|null $paypal_capture_id
 * @property int $amount_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quote $quote
 * @property-read Collection<int, PaymentAttempt> $attempts
 */
#[Fillable([
    'quote_id',
    'merchant_id',
    'cart_id',
    'order_number',
    'customer_name',
    'customer_email',
    'shipping_address',
    'shipping_method',
    'subtotal_cents',
    'shipping_cents',
    'paypal_order_id',
    'paypal_capture_id',
    'amount_cents',
    'currency',
    'status',
    'fulfilment_status',
    'courier',
    'tracking_number',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order): void {
            if (empty($order->order_number)) {
                $order->order_number = 'OP-'.strtoupper((string) Str::ulid());
            }
        });
    }

    /**
     * Get the line items in this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the merchant this order belongs to.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the quote this order was created from.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the PayPal calls made while settling this order.
     *
     * @return HasMany<PaymentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * Get the order amount formatted for display, for example "220.00".
     */
    public function formattedAmount(): string
    {
        return number_format($this->amount_cents / 100, 2, '.', '');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_address' => 'array',
        ];
    }
}
