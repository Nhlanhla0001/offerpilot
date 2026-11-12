<?php

namespace App\Services;

use App\Data\NegotiationResult;
use App\Enums\NegotiationStatus;
use App\Models\MerchantRule;
use App\Models\Product;
use InvalidArgumentException;

/**
 * Decides whether a buyer's offer can be met.
 *
 * Every price that leaves this service is calculated here, deterministically.
 * The merchant's floor never leaves this class: it is not returned, formatted
 * into a message, logged, or handed to the language model. Counters walk down
 * from the list price over the allowed rounds instead of jumping to the floor,
 * so the floor is not revealed on the first exchange.
 */
final class NegotiationService
{
    /**
     * The maximum number of alternative products offered when a budget cannot be met.
     */
    private const ALTERNATIVE_LIMIT = 3;

    /**
     * Resolve one negotiation round for the given product and buyer.
     */
    public function negotiate(
        Product $product,
        int $targetPriceCents,
        int $ceilingPriceCents,
        int $roundsUsed = 0,
    ): NegotiationResult {
        $this->assertValidBuyerLimits($targetPriceCents, $ceilingPriceCents);

        $rules = $product->rules;

        if ($rules === null) {
            return new NegotiationResult(
                status: NegotiationStatus::Rejected,
                priceCents: null,
                message: 'This product is not open to offers.',
                roundsUsed: $roundsUsed,
                canCounter: false,
            );
        }

        $floorPriceCents = $rules->floor_price_cents;

        if ($targetPriceCents >= $floorPriceCents) {
            $agreedPriceCents = min($targetPriceCents, $product->list_price_cents);

            return new NegotiationResult(
                status: NegotiationStatus::Accepted,
                priceCents: $agreedPriceCents,
                message: sprintf('The merchant accepted %s.', $this->money($agreedPriceCents)),
                roundsUsed: $roundsUsed,
                canCounter: false,
            );
        }

        if ($ceilingPriceCents < $floorPriceCents) {
            return $this->reject($product, $rules, $ceilingPriceCents, $roundsUsed);
        }

        if ($roundsUsed >= $rules->max_rounds) {
            $finalCounterCents = min(
                $this->concessionPrice(
                    $product->list_price_cents,
                    $floorPriceCents,
                    $rules->max_rounds - 1,
                    $rules->max_rounds,
                ),
                $ceilingPriceCents,
            );

            return new NegotiationResult(
                status: NegotiationStatus::Countered,
                priceCents: $finalCounterCents,
                message: sprintf(
                    'This is the shop’s final offer at %s.',
                    $this->money($finalCounterCents),
                ),
                roundsUsed: $roundsUsed,
                canCounter: false,
            );
        }

        $counterPriceCents = min(
            $this->concessionPrice(
                $product->list_price_cents,
                $floorPriceCents,
                $roundsUsed,
                $rules->max_rounds,
            ),
            $ceilingPriceCents,
        );

        return new NegotiationResult(
            status: NegotiationStatus::Countered,
            priceCents: $counterPriceCents,
            message: sprintf(
                'The merchant countered at %s, inside your %s ceiling.',
                $this->money($counterPriceCents),
                $this->money($ceilingPriceCents),
            ),
            roundsUsed: $roundsUsed + 1,
            canCounter: ($roundsUsed + 1) < $rules->max_rounds,
        );
    }

    /**
     * Walk down from the list price toward the floor as rounds are consumed.
     *
     * Round one starts a quarter of the way down, and the final round reaches the
     * floor. The result is rounded up to whole units so the ladder stays predictable.
     */
    private function concessionPrice(
        int $listPriceCents,
        int $floorPriceCents,
        int $roundsUsed,
        int $maxRounds,
    ): int {
        $fraction = ($roundsUsed + 1) / ($maxRounds + 1);
        $concession = (int) round($listPriceCents - ($listPriceCents - $floorPriceCents) * $fraction);
        $rounded = (int) (ceil($concession / 100) * 100);

        return max($floorPriceCents, min($listPriceCents, $rounded));
    }

    /**
     * Build a rejection, attaching in-budget alternatives when the merchant allows them.
     */
    private function reject(
        Product $product,
        MerchantRule $rules,
        int $ceilingPriceCents,
        int $roundsUsed,
    ): NegotiationResult {
        $alternatives = $rules->allow_alternatives
            ? $this->alternatives($product, $ceilingPriceCents)
            : [];

        return new NegotiationResult(
            status: NegotiationStatus::Rejected,
            priceCents: null,
            message: $alternatives === []
                ? 'The merchant cannot meet that budget for this item.'
                : 'That budget is below what the merchant can accept for this item, but these alternatives fit.',
            roundsUsed: $roundsUsed,
            canCounter: false,
            alternatives: $alternatives,
        );
    }

    /**
     * Get in-stock products from the same merchant that fit inside the buyer's ceiling.
     *
     * @return array<int, array{id: int, name: string, sku: string, list_price_cents: int}>
     */
    private function alternatives(Product $product, int $ceilingPriceCents): array
    {
        return Product::query()
            ->inStock()
            ->where('merchant_id', $product->merchant_id)
            ->whereKeyNot($product->getKey())
            ->where('list_price_cents', '<=', $ceilingPriceCents)
            ->orderBy('list_price_cents')
            ->orderBy('id')
            ->limit(self::ALTERNATIVE_LIMIT)
            ->get()
            ->map(fn (Product $alternative): array => [
                'id' => $alternative->id,
                'name' => $alternative->name,
                'sku' => $alternative->sku,
                'list_price_cents' => $alternative->list_price_cents,
            ])
            ->all();
    }

    /**
     * Ensure the buyer's mandate is coherent.
     */
    private function assertValidBuyerLimits(int $targetPriceCents, int $ceilingPriceCents): void
    {
        if ($targetPriceCents <= 0) {
            throw new InvalidArgumentException('The target price must be greater than zero.');
        }

        if ($ceilingPriceCents < $targetPriceCents) {
            throw new InvalidArgumentException('The maximum authorised spend cannot be below the target price.');
        }
    }

    /**
     * Format a minor-unit amount as a plain decimal string.
     */
    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
