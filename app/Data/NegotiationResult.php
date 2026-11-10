<?php

namespace App\Data;

use App\Enums\NegotiationStatus;

/**
 * The outcome of one negotiation round.
 *
 * Deliberately excludes the merchant floor: this object is safe to serialize to
 * the frontend and to hand to the language model.
 */
readonly class NegotiationResult
{
    /**
     * @param  array<int, array{id: int, name: string, sku: string, list_price_cents: int}>  $alternatives
     */
    public function __construct(
        public NegotiationStatus $status,
        public ?int $priceCents,
        public string $message,
        public int $roundsUsed,
        public bool $canCounter,
        public array $alternatives = [],
    ) {
        //
    }

    /**
     * Get the offered price formatted for display, for example "220.00".
     */
    public function formattedPrice(): ?string
    {
        return $this->priceCents === null
            ? null
            : number_format($this->priceCents / 100, 2, '.', '');
    }

    /**
     * Get the payload shape returned to the language model and the frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'price' => $this->formattedPrice(),
            'message' => $this->message,
            'rounds_used' => $this->roundsUsed,
            'can_counter' => $this->canCounter,
            'alternatives' => $this->alternatives,
        ];
    }
}
