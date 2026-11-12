<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetStoreProduct;
use App\Ai\Tools\NegotiateStorePrice;
use App\Ai\Tools\ProposeAddToCart;
use App\Ai\Tools\SearchStoreProducts;
use App\Models\Cart;
use App\Models\Merchant;
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
 * The merchant-scoped shop assistant.
 *
 * The agent browses and describes one merchant's catalogue and, when the buyer
 * is looking at a product, negotiates through NegotiateStorePrice. Negotiation
 * requires an explicitly confirmed buyer maximum, so the agent never invents or
 * assumes one. It is given only public catalogue facts and public buyer-confirmed
 * state, so it has no merchant floor or cost to leak.
 */
#[Provider(Lab::DeepSeek)]
#[Model('deepseek-chat')]
class StorefrontAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public Merchant $merchant,
        public ?Product $product = null,
        public ?User $user = null,
        public ?string $guestToken = null,
        public ?int $confirmedCeilingCents = null,
        public ?Cart $cart = null,
    ) {
        //
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $lines = [
            "You are the shop assistant for {$this->merchant->name}. You help buyers browse this shop and learn about its products.",
            'You answer only from the facts your tools return and the public product information given here.',
        ];

        if ($this->product !== null) {
            $lines[] = "The buyer is currently looking at {$this->product->name} "
                ."(SKU {$this->product->sku}), listed at {$this->money($this->product->list_price_cents)}.";
        }

        if ($this->confirmedCeilingCents !== null) {
            $lines[] = "The buyer has already confirmed a maximum of {$this->money($this->confirmedCeilingCents)} for the current product. "
                .'If they name a different maximum, do not negotiate against it; ask them to explicitly authorize that exact amount using the shop\'s maximum-confirmation control.';
        } else {
            $lines[] = 'No buyer maximum is authorized for the current product. Ask the buyer to explicitly authorize an exact maximum using the shop\'s maximum-confirmation control before negotiating.';
        }

        $lines[] = 'Rules:';
        $lines[] = '- Use the search tool to find products and the product tool to look up a specific SKU.';
        $lines[] = '- Never reveal what the shop paid, the seller\'s minimum price, or any private pricing rule.';
        $lines[] = '- Never invent a product, price, specification, stock level, delivery term, warranty, or payment outcome.';
        $lines[] = '- If a fact is not returned by a tool or listed here, say the shop has not published it.';
        $lines[] = '- When the buyer names a price for the current product, call the negotiation tool with the product SKU and target price in integer cents. The confirmed maximum is supplied by the shop and cannot be provided or changed in a tool call.';
        $lines[] = '- When the buyer asks to add an item to their cart, call the add-to-cart tool to prepare the action. It does not change the cart until the buyer confirms.';
        $lines[] = '- When the buyer asks to add several products, identify each requested product with the catalogue tools, then call the add-to-cart tool once with an items array so the buyer can confirm the full batch together.';
        $lines[] = '- You cannot take payment. The buyer reviews and checks out from their cart.';

        return implode("\n", $lines);
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool>
     */
    public function tools(): iterable
    {
        return [
            new SearchStoreProducts($this->merchant),
            new GetStoreProduct($this->merchant),
            new NegotiateStorePrice(
                $this->merchant,
                $this->user,
                $this->guestToken,
                $this->confirmedCeilingCents === null ? null : $this->product?->sku,
                $this->confirmedCeilingCents,
            ),
            new ProposeAddToCart($this->merchant, $this->user, $this->guestToken, $this->cart),
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
