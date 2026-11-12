<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\NegotiateStorePrice;
use App\Http\Requests\Storefront\AssistantRequest;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\BuyerSession;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

/**
 * Create the standalone storefront merchant used by these negotiation tests.
 */
function assistantNegotiationShop(): Merchant
{
    return Merchant::factory()->create(['name' => 'Northsound Audio']);
}

/**
 * Create a negotiable product with a private $210 floor and $85 cost.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $rules
 */
function assistantNegotiableProduct(Merchant $merchant, array $attributes = [], array $rules = []): Product
{
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'MIC-DM2',
        'name' => 'Northsound DM-2 USB Desk Microphone',
        'list_price_cents' => 25_000,
        'unit_cost_cents' => 8_500,
        'stock_quantity' => 5,
        ...$attributes,
    ]);

    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
        ...$rules,
    ]);

    return $product->refresh();
}

test('returns a clarification and performs no negotiation when no maximum is confirmed', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $tool = new NegotiateStorePrice($merchant, null, null);

    $json = (string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 20_000,
    ]));
    $payload = json_decode($json, true);

    expect($payload['status'])->toBe('clarification')
        ->and(Quote::query()->count())->toBe(0)
        ->and($json)->not->toContain('8500')
        ->and($json)->not->toContain('21000')
        ->and($json)->not->toContain('floor')
        ->and($json)->not->toContain('cost');
});

test('negotiates and persists the confirmed maximum on the quote when it clears the floor', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);
    $user = User::factory()->create();

    $tool = new NegotiateStorePrice($merchant, $user, null, 'MIC-DM2', 23_000);

    $json = (string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 22_000,
        'ceiling_price_cents' => 99_999,
    ]));
    $payload = json_decode($json, true);

    $quote = Quote::query()->firstOrFail();

    expect($payload['status'])->toBe('accepted')
        ->and($payload['price'])->toBe('220.00')
        ->and($payload['quote_id'])->toBe($quote->quote_id)
        ->and($quote->ceiling_price_cents)->toBe(23_000)
        ->and($quote->agreed_price_cents)->toBe(22_000)
        ->and($quote->user_id)->toBe($user->id)
        ->and($json)->not->toContain('21000')
        ->and($json)->not->toContain('8500');
});

test('counters an offer below the floor while it stays inside the confirmed maximum', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $tool = new NegotiateStorePrice($merchant, null, null, 'MIC-DM2', 25_000);

    $json = (string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 20_000,
        'ceiling_price_cents' => 1,
    ]));
    $payload = json_decode($json, true);

    expect($payload['status'])->toBe('countered')
        ->and($payload['price'])->toBe('240.00')
        ->and($payload['can_counter'])->toBeTrue()
        ->and(Quote::query()->count())->toBe(1)
        ->and($json)->not->toContain('21000')
        ->and($json)->not->toContain('8500');
});

test('rejects a maximum below the floor with buyer-safe alternatives and never reveals the floor', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $alternative = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Wireless Earbuds',
        'sku' => 'EARBUDS-1',
        'list_price_cents' => 17_500,
        'stock_quantity' => 3,
    ]);

    $tool = new NegotiateStorePrice($merchant, null, null, 'MIC-DM2', 19_000);

    $json = (string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 15_000,
        'ceiling_price_cents' => 99_999,
    ]));
    $payload = json_decode($json, true);

    expect($payload['status'])->toBe('rejected')
        ->and($payload['alternatives'])->toHaveCount(1)
        ->and($payload['alternatives'][0]['sku'])->toBe($alternative->sku)
        ->and(Quote::query()->count())->toBe(0)
        ->and($json)->not->toContain('21000')
        ->and($json)->not->toContain('8500');
});

test('treats a changed maximum as a new authorization and keeps the earlier quote immutable', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);
    $user = User::factory()->create();

    (new NegotiateStorePrice($merchant, $user, null, 'MIC-DM2', 22_500))->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 20_000,
        'ceiling_price_cents' => 1,
    ]));
    (new NegotiateStorePrice($merchant, $user, null, 'MIC-DM2', 25_000))->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 20_000,
        'ceiling_price_cents' => 99_999,
    ]));

    $quotes = Quote::query()->orderBy('id')->get();

    expect($quotes)->toHaveCount(2)
        ->and($quotes[0]->ceiling_price_cents)->toBe(22_500)
        ->and($quotes[1]->ceiling_price_cents)->toBe(25_000);
});

test('instructs the assistant to require explicit confirmation when the maximum changes', function () {
    $merchant = assistantNegotiationShop();
    $product = assistantNegotiableProduct($merchant);

    $instructions = (string) (new StorefrontAssistant($merchant, $product, null, null, 22_500))->instructions();

    expect($instructions)->toContain('225.00')
        ->and($instructions)->toContain('explicitly authorize')
        ->and($instructions)->not->toContain('21000')
        ->and($instructions)->not->toContain('8500');
});

test('the negotiation tool schema excludes the server-confirmed maximum', function () {
    $merchant = assistantNegotiationShop();

    $schema = (new NegotiateStorePrice($merchant, null, null))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['sku', 'target_price_cents']);
});

test('does not negotiate when only the model supplies a fabricated maximum', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $tool = new NegotiateStorePrice($merchant, null, null);

    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'MIC-DM2',
        'target_price_cents' => 22_000,
        'ceiling_price_cents' => 99_999,
    ])), true);

    expect($payload['status'])->toBe('clarification')
        ->and(Quote::query()->count())->toBe(0);
});

test('does not apply a confirmed maximum to another product sku', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'sku' => 'OTHER-1',
        'list_price_cents' => 30_000,
        'stock_quantity' => 2,
    ]);

    $tool = new NegotiateStorePrice($merchant, null, null, 'MIC-DM2', 23_000);
    $payload = json_decode((string) $tool->handle(new Request([
        'sku' => 'OTHER-1',
        'target_price_cents' => 22_000,
    ])), true);

    expect($payload['status'])->toBe('clarification')
        ->and(Quote::query()->count())->toBe(0);
});

test('stores a buyer-confirmed ceiling for the exact product in the current session', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Confirm maximum',
        'product_sku' => 'MIC-DM2',
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => 23_000,
    ])->assertOk()
        ->assertJsonPath('authorization.status', 'confirmed')
        ->assertJsonPath('authorization.product_sku', 'MIC-DM2')
        ->assertJsonPath('authorization.ceiling_price_cents', 23_000);

    $response->assertSessionHas(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY.'.guest-'.session(BuyerSession::KEY).'.MIC-DM2.ceiling_price_cents', 23_000);
});

test('a changed maximum replaces the session authorization only after explicit confirmation', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);
    $confirm = fn (int $amount) => $this->postJson(route('store.assistant'), [
        'message' => 'Confirm maximum',
        'product_sku' => 'MIC-DM2',
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => $amount,
    ]);

    $confirm(22_500)->assertOk()->assertSessionHas(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY.'.guest-'.session(BuyerSession::KEY).'.MIC-DM2.ceiling_price_cents', 22_500);

    $confirm(25_000)->assertOk()->assertSessionHas(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY.'.guest-'.session(BuyerSession::KEY).'.MIC-DM2.ceiling_price_cents', 25_000);
});

test('a guest ceiling authorization does not cross into a different session', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);

    $this->postJson(route('store.assistant'), [
        'message' => 'Confirm maximum',
        'product_sku' => 'MIC-DM2',
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => 23_000,
    ])->assertOk();

    $this->flushSession();
    StorefrontAssistant::fake(['No confirmed maximum is available.']);
    $response = $this->postJson(route('store.assistant'), [
        'message' => 'Negotiate the price',
        'product_sku' => 'MIC-DM2',
    ])->assertOk();

    $guestToken = session(BuyerSession::KEY);
    $response->assertSessionMissing(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY.'.guest-'.$guestToken.'.MIC-DM2');
    $this->assertDatabaseCount('quotes', 0);
});

test('a different authenticated buyer cannot use another buyer ceiling authorization', function () {
    $merchant = assistantNegotiationShop();
    assistantNegotiableProduct($merchant);
    $firstBuyer = User::factory()->create();
    $secondBuyer = User::factory()->create();

    $this->actingAs($firstBuyer)->postJson(route('store.assistant'), [
        'message' => 'Confirm maximum',
        'product_sku' => 'MIC-DM2',
        'confirmation_action' => 'confirm_ceiling',
        'ceiling_price_cents' => 23_000,
    ])->assertOk();

    StorefrontAssistant::fake(['No confirmed maximum is available.']);
    $secondBuyerResponse = $this->actingAs($secondBuyer)->postJson(route('store.assistant'), [
        'message' => 'Negotiate the price',
        'product_sku' => 'MIC-DM2',
    ])->assertOk();

    $secondBuyerResponse->assertSessionMissing(AssistantRequest::CONFIRMED_CEILINGS_SESSION_KEY.'.user-'.$secondBuyer->id.'.MIC-DM2');
    $this->assertDatabaseCount('quotes', 0);
});
