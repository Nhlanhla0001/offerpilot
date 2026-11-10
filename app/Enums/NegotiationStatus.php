<?php

namespace App\Enums;

enum NegotiationStatus: string
{
    case Accepted = 'accepted';
    case Countered = 'countered';
    case Rejected = 'rejected';

    /**
     * Determine whether the buyer can proceed to checkout.
     */
    public function isPurchasable(): bool
    {
        return $this === self::Accepted || $this === self::Countered;
    }
}
