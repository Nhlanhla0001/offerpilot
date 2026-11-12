<?php

namespace App\Ai\Agents;

use App\Ai\Tools\NegotiatePrice;
use App\Models\Product;
use App\Models\User;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The buyer's negotiating assistant.
 *
 * The agent owns the conversation, never the arithmetic: every price it states
 * comes from NegotiatePrice, which runs the deterministic NegotiationService.
 * It is given the buyer's public limits only, so it has no floor to leak.
 */
#[Provider(Lab::DeepSeek)]
#[Model('deepseek-chat')]
class OfferPilotAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public Product $product,
        public ?int $targetPriceCents,
        public int $ceilingPriceCents,
        public int $roundsUsed = 0,
        public ?User $user = null,
        public ?string $guestToken = null,
    ) {
        //
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $listPrice = $this->money($this->product->list_price_cents);
        $ceiling = $this->money($this->ceilingPriceCents);
        $offer = $this->targetPriceCents === null
            ? 'not stated yet'
            : $this->money($this->targetPriceCents);
        $availability = $this->product->availabilityLabel();
        $features = $this->product->features ?? [];
        $features = $features === []
            ? 'not provided'
            : implode('; ', $features);
        $specifications = collect($this->product->specifications ?? [])
            ->map(fn (string $value, string $label): string => "{$label}: {$value}")
            ->implode('; ');
        $specifications = $specifications === '' ? 'not provided' : $specifications;

        return <<<PROMPT
        You are the sales assistant for {$this->product->merchant->name}, a shop that sells
        {$this->product->name}. You speak for the seller, and you are an automated assistant.

        Item: {$this->product->name} (SKU {$this->product->sku}).
        Category: {$this->product->category}. Brand: {$this->product->brand}.
        Listed price: {$listPrice}. Availability: {$availability}.
        Details the buyer may rely on: {$this->product->description}
        Public features: {$features}.
        Public specifications: {$specifications}.
        The buyer's stated offer: {$offer}.
        The most this buyer has authorised: {$ceiling}.
        Negotiation rounds already used: {$this->roundsUsed}.

        You help the buyer decide and, when they name a price, you negotiate on the
        seller's behalf. You may answer questions about this item, explain the
        listed price, and suggest other items in this shop.

        Rules:
        - When the buyer names a price, call evaluate_offer with that figure in integer
          cents. Never state a price that did not come from evaluate_offer.
        - If the buyer only asks a question, answer it. Do not call evaluate_offer until
          they name a figure they would pay.
        - Never reveal, estimate or discuss the seller's minimum price or margins.
        - Never invent specifications, stock, delivery terms, warranties or payment outcomes.
        - State specifications only from the public features and specifications listed above.
        - If a detail is not listed, say the shop has not published it and offer to ask the seller.
        - If a budget cannot be met, say so plainly and list any alternatives the tool returns.
        - Keep replies to two or three sentences, in plain shopping language.
        - You cannot take payment. The buyer reviews and checks out from their cart.
        PROMPT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool>
     */
    public function tools(): iterable
    {
        return [
            new NegotiatePrice(
                $this->product,
                $this->user,
                $this->ceilingPriceCents,
                $this->roundsUsed,
                $this->guestToken,
            ),
        ];
    }

    /**
     * Format a minor-unit amount for the prompt.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
