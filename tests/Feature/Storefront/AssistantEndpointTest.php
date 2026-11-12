<?php

use App\Ai\Agents\OfferPilotAgent;
use App\Ai\Agents\StorefrontAssistant;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function () {
    config(['ai.conversations.generate_title' => false]);
});

test('the assistant endpoint returns the buyer-safe json contract', function () {
    Merchant::factory()->create();

    StorefrontAssistant::fake(['Here are some options in this shop.']);

    $response = $this->postJson(route('store.assistant'), ['message' => 'Do you have any microphones?']);

    $response->assertOk()
        ->assertJsonStructure([
            'reply',
            'products',
            'quote',
            'actions',
            'conversation_id',
        ])
        ->assertJsonPath('reply', 'Here are some options in this shop.')
        ->assertJsonPath('products', [])
        ->assertJsonPath('quote', null)
        ->assertJsonPath('actions', [])
        ->assertJsonPath('conversation_id', fn ($id) => is_string($id) && $id !== '');
});

test('the assistant endpoint validates the message', function () {
    Merchant::factory()->create();

    $this->postJson(route('store.assistant'), [])->assertUnprocessable();
    $this->postJson(route('store.assistant'), ['message' => str_repeat('a', 2001)])->assertUnprocessable();
});

test('the assistant endpoint responds gracefully when no merchant is configured', function () {
    $response = $this->postJson(route('store.assistant'), ['message' => 'Hello?']);

    $response->assertOk()
        ->assertJsonPath('reply', 'This shop has not published a catalogue yet.')
        ->assertJsonPath('products', [])
        ->assertJsonPath('quote', null)
        ->assertJsonPath('actions', []);
});

test('the assistant endpoint rejects a sku outside the current shop', function () {
    $merchant = Merchant::factory()->create();
    $other = Merchant::factory()->create();
    Product::factory()->create(['merchant_id' => $other->id, 'sku' => 'FOREIGN-1']);

    StorefrontAssistant::fake(['Hello']);

    $this->postJson(route('store.assistant'), ['message' => 'hi', 'product_sku' => 'FOREIGN-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('product_sku');
});

test('the assistant endpoint rejects an unknown sku', function () {
    Merchant::factory()->create();

    StorefrontAssistant::fake(['Hello']);

    $this->postJson(route('store.assistant'), ['message' => 'hi', 'product_sku' => 'MISSING-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('product_sku');
});

test('the assistant endpoint surfaces the focused product summary without private fields', function () {
    $merchant = Merchant::factory()->create();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
        'unit_cost_cents' => 12_500,
        'list_price_cents' => 25_000,
    ]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    StorefrontAssistant::fake(['That product is in stock.']);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Tell me about it',
        'product_sku' => 'HEADPHONES-PRO',
    ]);

    $response->assertOk()
        ->assertJsonPath('products.0.sku', 'HEADPHONES-PRO')
        ->assertJsonPath('products.0.name', 'Studio Headphones')
        ->assertJsonPath('products.0.image', $product->imageUrl())
        ->assertJsonPath('products.0.availability', '10 in stock')
        ->assertJsonPath('products.0.price', '250.00');

    $body = json_encode($response->json());
    expect($body)->not->toContain('unit_cost_cents')
        ->and($body)->not->toContain('floor_price_cents')
        ->and($body)->not->toContain('12500')
        ->and($body)->not->toContain('21000');
});

test('the assistant endpoint surfaces a pending quote and its confirmation action', function () {
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'HEADPHONES-PRO',
        'list_price_cents' => 25_000,
    ]);
    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'status' => 'pending',
        'agreed_price_cents' => 22_000,
        'expires_at' => now()->addMinutes(10),
    ]);

    StorefrontAssistant::fake(['Your offer is held.']);

    $response = $this->actingAs($user)->postJson(route('store.assistant'), [
        'message' => 'What price did we agree?',
        'product_sku' => 'HEADPHONES-PRO',
    ]);

    $response->assertOk()
        ->assertJsonPath('quote.id', $quote->quote_id)
        ->assertJsonPath('quote.price', '220.00')
        ->assertJsonPath('actions.0.type', 'confirm_quote')
        ->assertJsonPath('actions.0.quote_id', $quote->quote_id);
});

test('the assistant endpoint surfaces authorized alternatives returned by negotiation', function () {
    $merchant = Merchant::factory()->create();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'PREMIUM-1',
        'name' => 'Premium Headphones',
        'list_price_cents' => 25_000,
    ]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'BUDGET-1',
        'name' => 'Budget Headphones',
        'list_price_cents' => 18_000,
    ]);

    $this->postJson(route('store.assistant'), [
        'message' => 'Confirm my maximum',
        'product_sku' => 'PREMIUM-1',
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => 19_000,
    ])->assertOk();

    StorefrontAssistant::fake([
        new ToolCall('call-alternatives', 'NegotiateStorePrice', [
            'sku' => 'PREMIUM-1',
            'target_price_cents' => 18_000,
        ]),
        'The budget is below the premium item; here is a suitable alternative.',
    ]);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Can you do 180?',
        'product_sku' => 'PREMIUM-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('products.1.sku', 'BUDGET-1')
        ->assertJsonPath('products.1.name', 'Budget Headphones');
});

test('a conversation id must belong to the current buyer session', function () {
    Merchant::factory()->create();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    StorefrontAssistant::fake(['First reply.']);
    $first = $this->actingAs($owner)->postJson(route('store.assistant'), ['message' => 'Hello']);
    $first->assertOk();

    $conversationId = $first->json('conversation_id');
    expect($conversationId)->not->toBeNull();

    StorefrontAssistant::fake(['Second reply.']);
    $this->actingAs($intruder)->postJson(route('store.assistant'), [
        'message' => 'Hi',
        'conversation_id' => $conversationId,
    ])->assertUnprocessable()->assertJsonValidationErrors('conversation_id');
});

test('a guest conversation continues within its session and rejects foreign ids', function () {
    Merchant::factory()->create();

    StorefrontAssistant::fake(['First reply.', 'Second reply.']);

    $first = $this->postJson(route('store.assistant'), ['message' => 'Hello']);
    $first->assertOk();

    $conversationId = $first->json('conversation_id');
    expect($conversationId)->not->toBeNull();

    // The same guest session may resume its own conversation.
    $second = $this->postJson(route('store.assistant'), [
        'message' => 'Tell me more.',
        'conversation_id' => $conversationId,
    ]);
    $second->assertOk()->assertJsonPath('conversation_id', $conversationId);

    // A different session must not be able to resume it.
    $this->flushSession();
    StorefrontAssistant::fake(['Third reply.']);
    $this->postJson(route('store.assistant'), [
        'message' => 'Hi',
        'conversation_id' => $conversationId,
    ])->assertUnprocessable()->assertJsonValidationErrors('conversation_id');
});

test('the existing negotiation endpoint keeps its reply and quote contract', function () {
    $product = Product::factory()->create(['list_price_cents' => 25_000]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $this->postJson(route('store.assistant'), [
        'message' => 'Confirm my maximum',
        'product_sku' => $product->sku,
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => 25_000,
    ])->assertOk();

    OfferPilotAgent::fake(['The merchant countered at 240.00.']);

    $response = $this->postJson(route('store.negotiate', $product->sku), [
        'message' => 'Can you do 200?',
        'target_price_cents' => 20_000,
        'ceiling_price_cents' => 25_000,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['reply', 'quote'])
        ->assertJsonPath('reply', 'The merchant countered at 240.00.');
});
