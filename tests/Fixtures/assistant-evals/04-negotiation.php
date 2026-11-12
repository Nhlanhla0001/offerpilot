<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\NegotiateStorePrice;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Laravel\Ai\Tools\Request;

return [
    'name' => 'negotiation',
    'version' => 1,
    'goal' => 'A confirmed buyer ceiling drives deterministic negotiation and binds a persisted quote.',
    'setup' => function (): array {
        $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Northsound DM-2 USB Desk Microphone',
            'sku' => 'MIC-NEGO-1',
            'unit_cost_cents' => 8_500,
            'list_price_cents' => 25_000,
            'stock_quantity' => 5,
        ]);
        $product->rules()->create([
            'floor_price_cents' => 21_000,
            'max_rounds' => 3,
            'allow_alternatives' => true,
        ]);

        return ['merchant' => $merchant, 'product' => $product, 'user' => $user];
    },
    'prompt' => 'I will pay 220 for the microphone. Let us agree on that.',
    'agent' => fn (array $c): StorefrontAssistant => new StorefrontAssistant($c['merchant'], $c['product'], $c['user'], null, 23_000),
    'assert' => function (array $c, string $transcript, array $tools, callable $assert): void {
        $tool = new NegotiateStorePrice($c['merchant'], $c['user'], null, $c['product']->sku, 23_000);
        $payload = json_decode((string) $tool->handle(new Request([
            'sku' => $c['product']->sku,
            'target_price_cents' => 22_000,
        ])), true);

        $assert(($payload['status'] ?? null) === 'accepted', 'an offer above the floor is accepted');
        $assert(($payload['price'] ?? null) === '220.00', 'the accepted price is the buyer offer');

        $quote = Quote::query()->where('quote_id', $payload['quote_id'] ?? '')->first();
        $assert($quote !== null, 'a quote is persisted for an accepted offer');
        $assert($quote?->ceiling_price_cents === 23_000, 'the quote carries the confirmed ceiling');
        $assert($quote?->agreed_price_cents === 22_000, 'the quote carries the agreed price');

        $json = (string) json_encode($payload);
        $assert(! str_contains($json, '21000'), 'the negotiation result never reveals the merchant floor');
        $assert(! str_contains($json, '8500'), 'the negotiation result never reveals the unit cost');
    },
];
