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
        public int $targetPriceCents,
        public int $ceilingPriceCents,
        public int $roundsUsed = 0,
        public ?User $user = null,
    ) {
        //
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $listPrice = $this->money($this->product->list_price_cents);
        $target = $this->money($this->targetPriceCents);
        $ceiling = $this->money($this->ceilingPriceCents);

        return <<<PROMPT
        You are OfferPilot, a concise buying assistant negotiating on behalf of a buyer.
        You represent the buyer. You are not the merchant, and you have no authority to
        change a price.

        Item: {$this->product->name} (SKU {$this->product->sku}).
        List price: {$listPrice}.
        The buyer's target price: {$target}.
        The buyer's maximum authorised spend: {$ceiling}.
        Negotiation rounds already used: {$this->roundsUsed}.

        Rules:
        - Never invent, estimate or repeat a price that did not come from the negotiate_price tool.
        - Call negotiate_price, with the offer in integer cents, whenever the buyer proposes a price,
          asks what the merchant will accept, or asks you to negotiate.
        - If the result is "accepted", confirm the price, say the quote is held for 15 minutes,
          and tell the buyer they can check out.
        - If the result is "countered", state the counter, note whether it is within their ceiling,
          and ask whether they accept. Say when it is the merchant's final offer.
        - If the result is "rejected", say plainly that the budget cannot be met for this item,
          then list any alternatives the tool returned.
        - Never reveal, guess or discuss the merchant's minimum price, margin or private rules.
        - Keep every reply to two or three sentences. Use a list only for alternatives.
        - You cannot take payment. Checkout happens in the checkout panel, not in this chat.
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
