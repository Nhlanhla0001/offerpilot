<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\ProposeAddToCart;
use App\Http\Requests\Storefront\AssistantRequest;
use App\Models\Cart;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\CartService;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;

/**
 * Create the standalone storefront merchant used by these cart tests.
 */
function assistantCartShop(): Merchant
{
    return Merchant::factory()->create(['name' => 'Northsound Audio']);
}

test('proposing a cart action returns a proposal and mutates nothing', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);

    $tool = new ProposeAddToCart($merchant, null, null);

    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'quantity' => 2,
    ])), true);

    expect($payload['status'])->toBe('proposed')
        ->and($payload['action']['sku'])->toBe('MIC-DM2')
        ->and($payload['action']['quantity'])->toBe(2)
        ->and($payload['action']['price'])->toBe('250.00')
        ->and(Cart::query()->count())->toBe(0);
});

test('proposing several cart items returns one batch proposal and mutates nothing', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'name' => 'Desk Microphone',
        'list_price_cents' => 12_000,
        'stock_quantity' => 5,
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'KEY-K1',
        'name' => 'Mechanical Keyboard',
        'list_price_cents' => 18_500,
        'stock_quantity' => 4,
    ]);

    $tool = new ProposeAddToCart($merchant, null, null);

    $payload = json_decode((string) $tool->handle(new Request([
        'items' => [
            ['sku' => 'MIC-DM2', 'quantity' => 2],
            ['sku' => 'KEY-K1', 'quantity' => 1],
        ],
    ])), true);

    expect($payload['status'])->toBe('proposed')
        ->and($payload['action']['items'])->toHaveCount(2)
        ->and($payload['action']['items'][0]['sku'])->toBe('MIC-DM2')
        ->and($payload['action']['items'][0]['quantity'])->toBe(2)
        ->and($payload['action']['items'][1]['sku'])->toBe('KEY-K1')
        ->and($payload['action']['items'][1]['quantity'])->toBe(1)
        ->and(Cart::query()->count())->toBe(0);
});

test('proposing a batch with an unavailable product returns no partial cart action', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);

    $tool = new ProposeAddToCart($merchant, null, null);

    $payload = json_decode((string) $tool->handle(new Request([
        'items' => [
            ['sku' => 'MIC-DM2', 'quantity' => 1],
            ['sku' => 'MISSING-1', 'quantity' => 1],
        ],
    ])), true);

    expect($payload['status'])->toBe('unavailable')
        ->and($payload)->not->toHaveKey('action')
        ->and(Cart::query()->count())->toBe(0);
});

test('proposing an item with an invalid negotiated quote does not fall back to list price', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);

    $tool = new ProposeAddToCart($merchant, null, 'guest-token');

    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'quote_id' => 'expired-or-foreign-quote',
    ])), true);

    expect($payload['status'])->toBe('unavailable')
        ->and($payload)->not->toHaveKey('action')
        ->and($payload['message'])->toContain('agreed price')
        ->and(Cart::query()->count())->toBe(0);
});

test('a proposal clamps its quantity to available stock', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 2,
    ]);

    $tool = new ProposeAddToCart($merchant, null, null);

    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'quantity' => 9,
    ])), true);

    expect($payload['action']['quantity'])->toBe(2);
});

test('a proposal accounts for quantity already held in the cart', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 3,
    ]);
    $cartService = app(CartService::class);
    $cart = $cartService->forSession('proposal-existing-quantity');
    $cartService->add($cart, $product, 2);

    $tool = new ProposeAddToCart($merchant, null, null, $cart);

    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'quantity' => 5,
    ])), true);

    expect($payload['status'])->toBe('proposed')
        ->and($payload['action']['quantity'])->toBe(1);
});

test('a proposal never reveals the merchant floor or cost', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'unit_cost_cents' => 8_500,
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $tool = new ProposeAddToCart($merchant, null, null);

    $json = (string) $tool->handle(new Request(['sku' => 'MIC-DM2']));

    expect($json)->not->toContain('8500')
        ->and($json)->not->toContain('21000')
        ->and($json)->not->toContain('floor')
        ->and($json)->not->toContain('cost');
});

test('a confirmed cart action adds the item and returns a recomputed summary', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);

    $response = $this->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 2, 'quote_id' => null],
        ],
    ])->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ]);

    $response->assertOk()
        ->assertJsonPath('cart.items.0.sku', 'MIC-DM2')
        ->assertJsonPath('cart.items.0.quantity', 2)
        ->assertJsonPath('cart.subtotal_cents', 50_000);
});

test('one confirmed assistant batch adds multiple products and returns the full cart', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'name' => 'Desk Microphone',
        'list_price_cents' => 12_000,
        'stock_quantity' => 5,
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'KEY-K1',
        'name' => 'Mechanical Keyboard',
        'list_price_cents' => 18_500,
        'stock_quantity' => 4,
    ]);

    StorefrontAssistant::fake([
        new ToolCall('call-microphone', 'ProposeAddToCart', [
            'sku' => 'MIC-DM2',
            'quantity' => 2,
        ]),
        new ToolCall('call-keyboard', 'ProposeAddToCart', [
            'sku' => 'KEY-K1',
            'quantity' => 1,
        ]),
        'I prepared both items for your confirmation.',
    ]);

    $proposalResponse = $this->postJson(route('store.assistant'), [
        'message' => 'Add two microphones and one keyboard.',
    ]);

    $proposalResponse->assertOk()
        ->assertJsonPath('actions.0.type', 'confirm_add_to_cart')
        ->assertJsonPath('actions.0.items.0.sku', 'MIC-DM2')
        ->assertJsonPath('actions.0.items.0.quantity', 2)
        ->assertJsonPath('actions.0.items.1.sku', 'KEY-K1')
        ->assertJsonPath('actions.0.items.1.quantity', 1);

    expect(Cart::query()->count())->toBe(0);

    $confirmationResponse = $this->postJson(route('store.assistant'), [
        'message' => 'Yes, add both',
        'action_id' => $proposalResponse->json('actions.0.action_id'),
    ]);

    $confirmationResponse->assertOk()
        ->assertJsonPath('cart.items.0.sku', 'MIC-DM2')
        ->assertJsonPath('cart.items.0.quantity', 2)
        ->assertJsonPath('cart.items.1.sku', 'KEY-K1')
        ->assertJsonPath('cart.items.1.quantity', 1)
        ->assertJsonPath('cart.subtotal_cents', 42_500);
});

test('separate assistant cart proposals do not offer a partial batch when an item is unavailable', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);

    StorefrontAssistant::fake([
        new ToolCall('call-available', 'ProposeAddToCart', [
            'sku' => 'MIC-DM2',
            'quantity' => 1,
        ]),
        new ToolCall('call-unavailable', 'ProposeAddToCart', [
            'sku' => 'MISSING-1',
            'quantity' => 1,
        ]),
        'One requested item is unavailable.',
    ]);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Add one microphone and one unavailable cable.',
    ]);

    $response->assertOk()
        ->assertJsonPath('actions', [])
        ->assertSessionMissing(AssistantRequest::CART_PROPOSALS_SESSION_KEY);

    expect(Cart::query()->count())->toBe(0);
});

test('a batch confirmation adds none when available quantity falls below the requested amount', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);
    $keyboard = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'KEY-K1',
        'stock_quantity' => 4,
    ]);

    $this->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'batch-action-id' => [
                'items' => [
                    ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => null],
                    ['sku' => 'KEY-K1', 'quantity' => 2, 'quote_id' => null],
                ],
            ],
        ],
    ]);
    $keyboard->update(['stock_quantity' => 1]);

    $this->postJson(route('store.assistant'), [
        'message' => 'Yes, add both',
        'action_id' => 'batch-action-id',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'One or more items changed in availability or quantity. Nothing was added.');

    expect(Cart::query()->count())->toBe(0);
});

test('separate assistant proposals with duplicate products do not create an ambiguous batch', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);

    StorefrontAssistant::fake([
        new ToolCall('call-first', 'ProposeAddToCart', ['sku' => 'MIC-DM2', 'quantity' => 1]),
        new ToolCall('call-second', 'ProposeAddToCart', ['sku' => 'MIC-DM2', 'quantity' => 1]),
        'I need to clarify the duplicate item request.',
    ]);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Add two microphones.',
    ]);

    $response->assertOk()
        ->assertJsonPath('actions', [])
        ->assertSessionMissing(AssistantRequest::CART_PROPOSALS_SESSION_KEY);
});

test('a batch confirmation adds none when one negotiated price expires', function () {
    $merchant = assistantCartShop();
    $microphone = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'KEY-K1',
        'stock_quantity' => 4,
    ]);
    $buyer = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $buyer->id,
        'product_id' => $microphone->id,
        'agreed_price_cents' => 11_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($buyer)->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'batch-action-id' => [
                'items' => [
                    ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => $quote->quote_id],
                    ['sku' => 'KEY-K1', 'quantity' => 1, 'quote_id' => null],
                ],
            ],
        ],
    ]);
    $quote->update(['status' => 'expired', 'expires_at' => now()->subMinute()]);

    $this->postJson(route('store.assistant'), [
        'message' => 'Yes, add both',
        'action_id' => 'batch-action-id',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The agreed price is no longer available. Please ask the assistant again.');

    expect(Cart::query()->count())->toBe(0);
});

test('a confirmed cart action is single-use', function () {
    $merchant = assistantCartShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);

    $this->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => null],
        ],
    ])->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ])->assertOk();

    $this->postJson(route('store.assistant'), [
        'message' => 'Add it again',
        'action_id' => 'test-action-id',
    ])->assertUnprocessable();
});

test('a confirmed cart action re-resolves availability and rejects an item that is now out of stock', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'stock_quantity' => 5,
    ]);

    $this->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => null],
        ],
    ]);

    $product->update(['stock_quantity' => 0]);

    $this->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ])->assertUnprocessable()->assertJsonPath('message', 'This item is not available right now.');
});

test('a confirmed cart action honours a valid quote owned by the buyer', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $user = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => $quote->quote_id],
        ],
    ])->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ]);

    $response->assertOk()
        ->assertJsonPath('cart.items.0.unit_price_cents', 22_000)
        ->assertJsonPath('cart.items.0.negotiated', true)
        ->assertJsonPath('cart.items.0.quantity', 1);
});

test('a confirmed cart action rejects a quote that belongs to another buyer', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $other->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($buyer)->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => $quote->quote_id],
        ],
    ])->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'The agreed price is no longer available. Please ask the assistant again.');

    expect(Cart::query()->count())->toBe(0);
});

test('a confirmed cart action rejects a quote that has expired', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $user = User::factory()->create();
    $quote = Quote::factory()->expired()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
    ]);

    $response = $this->actingAs($user)->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => $quote->quote_id],
        ],
    ])->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'The agreed price is no longer available. Please ask the assistant again.');

    expect(Cart::query()->count())->toBe(0);
});

test('a quote-bound proposal rejects without mutation when its quote expires before confirmation', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $user = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)->withSession([
        AssistantRequest::CART_PROPOSALS_SESSION_KEY => [
            'test-action-id' => ['sku' => 'MIC-DM2', 'quantity' => 1, 'quote_id' => $quote->quote_id],
        ],
    ]);

    // The quote expires after the proposal was staged but before final confirmation.
    $quote->update(['status' => 'expired', 'expires_at' => now()->subMinute()]);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => 'test-action-id',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'The agreed price is no longer available. Please ask the assistant again.');

    expect(Cart::query()->count())->toBe(0);

    // The stale proposal is left in place, not silently consumed.
    $response->assertSessionHas(AssistantRequest::CART_PROPOSALS_SESSION_KEY.'.test-action-id.sku', 'MIC-DM2');
});

test('a confirmed quote becomes a cart proposal bound to its exact quote and product', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'HEADPHONES-PRO',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $user = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->postJson(route('store.assistant'), [
        'confirmation_action' => 'confirm_quote_to_cart',
        'product_sku' => 'HEADPHONES-PRO',
        'quote_id' => $quote->quote_id,
    ]);

    $response->assertOk()
        ->assertJsonPath('actions.0.type', 'confirm_add_to_cart')
        ->assertJsonPath('actions.0.sku', 'HEADPHONES-PRO')
        ->assertJsonPath('actions.0.quote_id', $quote->quote_id)
        ->assertJsonPath('actions.0.price', '220.00')
        ->assertJsonPath('actions.0.quantity', 1);

    // The proposal must not mutate the cart on its own.
    expect(Cart::query()->count())->toBe(0);

    // The buyer still has to explicitly confirm the proposal before any mutation.
    $actionId = $response->json('actions.0.action_id');
    $this->actingAs($user)->postJson(route('store.assistant'), [
        'message' => 'Yes, add it',
        'action_id' => $actionId,
    ])->assertOk()
        ->assertJsonPath('cart.items.0.sku', 'HEADPHONES-PRO')
        ->assertJsonPath('cart.items.0.unit_price_cents', 22_000)
        ->assertJsonPath('cart.items.0.negotiated', true)
        ->assertJsonPath('cart.items.0.quantity', 1);
});

test('a confirmed quote cannot be proposed for a different product sku', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'HEADPHONES-PRO',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'OTHER-1',
        'list_price_cents' => 30_000,
        'stock_quantity' => 5,
    ]);
    $user = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($user)->postJson(route('store.assistant'), [
        'confirmation_action' => 'confirm_quote_to_cart',
        'product_sku' => 'OTHER-1',
        'quote_id' => $quote->quote_id,
    ])->assertUnprocessable();

    expect(Cart::query()->count())->toBe(0);
});

test('a confirmed quote cannot be proposed for another buyer', function () {
    $merchant = assistantCartShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'HEADPHONES-PRO',
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
    ]);
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $quote = Quote::factory()->create([
        'user_id' => $other->id,
        'product_id' => $product->id,
        'agreed_price_cents' => 22_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($buyer)->postJson(route('store.assistant'), [
        'confirmation_action' => 'confirm_quote_to_cart',
        'product_sku' => 'HEADPHONES-PRO',
        'quote_id' => $quote->quote_id,
    ])->assertUnprocessable();

    expect(Cart::query()->count())->toBe(0);
});
