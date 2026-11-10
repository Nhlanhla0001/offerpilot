<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Fulfilled = 'fulfilled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Determine if the quote can still be accepted.
     */
    public function isAcceptable(): bool
    {
        return $this === self::Pending;
    }
}
