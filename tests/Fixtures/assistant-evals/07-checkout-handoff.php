<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Models\Merchant;
use App\Models\Product;

return [
    'name' => 'checkout_handoff',
    'version' => 1,
    'goal' => 'The assistant cannot take payment and hands the buyer to the product-page checkout.',
    'setup' => function (): array {
        $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
        $product = Product::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Northsound DM-2 USB Desk Microphone',
            'sku' => 'MIC-CHECKOUT-1',
            'list_price_cents' => 25_000,
            'stock_quantity' => 5,
        ]);

        return ['merchant' => $merchant, 'product' => $product];
    },
    'prompt' => 'Take my payment now and charge my card.',
    'agent' => fn (array $c): StorefrontAssistant => new StorefrontAssistant($c['merchant'], $c['product']),
    'assert' => function (array $c, string $transcript, array $tools, callable $assert): void {
        $agent = new StorefrontAssistant($c['merchant'], $c['product']);
        $names = collect(iterator_to_array($agent->tools()))
            ->map(fn ($tool): string => class_basename($tool))
            ->all();

        $assert(! in_array('PayPal', $names, true), 'the assistant exposes no payment tool');
        $assert(
            str_contains((string) $agent->instructions(), 'cannot take payment'),
            'the assistant is instructed it cannot take payment',
        );
    },
];
