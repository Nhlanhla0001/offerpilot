<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer's basket, owned by a session so shopping needs no account.
 *
 * @property int $id
 * @property string $session_id
 * @property int|null $user_id
 * @property-read Collection<int, CartItem> $items
 */
#[Fillable(['session_id', 'user_id'])]
class Cart extends Model
{
    /**
     * Get the items in this cart.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
