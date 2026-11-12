<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single, PII-free first-party storefront interaction.
 *
 * Rows hold only an allowlisted event name, the assigned experiment arm, the
 * merchant id, a device class, and any allowlisted categorical properties. No
 * session, visitor, message, payment, or product identifying data is stored.
 *
 * @property int $id
 * @property string $name
 * @property string|null $variant
 * @property int|null $merchant_id
 * @property string|null $device_class
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant|null $merchant
 */
#[Fillable(['name', 'variant', 'merchant_id', 'device_class', 'properties'])]
class StorefrontEvent extends Model
{
    /**
     * Get the merchant this event was recorded against.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }
}
