<?php

namespace App\Enums;

enum PaymentAction: string
{
    case Create = 'create';
    case Capture = 'capture';

    /**
     * Get the display label for the action.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
