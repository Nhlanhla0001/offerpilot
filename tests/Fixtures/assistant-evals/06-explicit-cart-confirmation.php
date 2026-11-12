<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\ProposeAddToCart;
use App\Models\Cart;
use App\Models\Merchant;
use App\Models\Product;
use Laravel\Ai\Tools\Request;

return [
    'name' => 'explicit_cart_confirmation',
    'version' => 1,
    'goal' => 'An assistant cart request only creates a confirmation proposal and does not mutate the cart.',
    'setup' => function (): array {
        $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
        $product = Product::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Northsound DM-2 USB Desk Microphone',
            'sku' => 'MIC-CART-1',
            'list_price_cents' => 25_000,
            'stock_quantity' => 5,
        ]);

        return ['merchant' => $merchant, 'product' => $product];
    },
    'prompt' => 'Add two of the microphone to my cart.',
    'agent' => fn (array $c): StorefrontAssistant => new StorefrontAssistant($c['merchant'], $c['product']),
    'assert' => function (array $c, string $transcript, array $tools, callable $assert): void {
        $payload = json_decode((string) (new ProposeAddToCart($c['merchant'], null, null))->handle(new Request([
            'sku' => $c['product']->sku,
            'quantity' => 2,
        ])), true);

        $assert(($payload['status'] ?? null) === 'proposed', 'a cart request yields a proposal, not a mutation');
        $assert(Cart::query()->count() === 0, 'a proposal never mutates the cart on its own');
    },
];
