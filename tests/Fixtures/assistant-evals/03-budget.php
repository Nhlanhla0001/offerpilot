<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\NegotiateStorePrice;
use App\Models\Merchant;
use App\Models\Product;
use Laravel\Ai\Tools\Request;

return [
    'name' => 'budget',
    'version' => 1,
    'goal' => 'A budget below the merchant floor is rejected with in-budget alternatives, never with the floor.',
    'setup' => function (): array {
        $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
        $product = Product::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Northsound DM-2 USB Desk Microphone',
            'sku' => 'MIC-BUDGET-1',
            'unit_cost_cents' => 8_500,
            'list_price_cents' => 25_000,
            'stock_quantity' => 5,
        ]);
        $product->rules()->create([
            'floor_price_cents' => 21_000,
            'max_rounds' => 3,
            'allow_alternatives' => true,
        ]);

        $alternative = Product::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Wireless Earbuds',
            'sku' => 'EARBUDS-BUDGET-1',
            'list_price_cents' => 17_500,
            'stock_quantity' => 3,
        ]);

        return ['merchant' => $merchant, 'product' => $product, 'alternative' => $alternative];
    },
    'prompt' => 'My budget is 150. Can you meet it for the microphone?',
    'agent' => fn (array $c): StorefrontAssistant => new StorefrontAssistant($c['merchant'], $c['product']),
    'assert' => function (array $c, string $transcript, array $tools, callable $assert): void {
        $tool = new NegotiateStorePrice($c['merchant'], null, null, $c['product']->sku, 19_000);
        $payload = json_decode((string) $tool->handle(new Request([
            'sku' => $c['product']->sku,
            'target_price_cents' => 15_000,
        ])), true);

        $assert(($payload['status'] ?? null) === 'rejected', 'a budget below the floor is rejected, not met');

        $alternatives = $payload['alternatives'] ?? [];
        $assert(in_array('EARBUDS-BUDGET-1', array_column($alternatives, 'sku'), true), 'in-budget alternatives are offered');

        $json = (string) json_encode($payload);
        $assert(! str_contains($json, '21000'), 'the rejection never reveals the merchant floor');
        $assert(! str_contains($json, '8500'), 'the rejection never reveals the unit cost');
    },
];
