<?php

use App\Ai\Agents\OfferPilotAgent;
use App\Ai\Tools\NegotiatePrice;
use App\Models\Product;
use App\Models\User;
use App\Services\NegotiationService;
use Laravel\Ai\Tools\Request;

/**
 * Create a $250 product with a private $210 floor.
 */
function offerPilotProductForAgent(): Product
{
    $product = Product::factory()->create([
        'name' => 'Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
        'list_price_cents' => 25_000,
        'unit_cost_cents' => 8_500,
        'stock_quantity' => 4,
        'features' => ['Closed-back drivers', 'Replaceable earpads'],
        'specifications' => ['Impedance' => '38 Ω'],
    ]);

    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    return $product->refresh();
}

function offerPilotAgent(Product $product, int $target = 20_000, int $ceiling = 22_500): OfferPilotAgent
{
    return new OfferPilotAgent($product, $target, $ceiling);
}

test('exposes the price tool and nothing that can invent a price', function () {
    $agent = offerPilotAgent(offerPilotProductForAgent());

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(1)
        ->and($tools[0])->toBeInstanceOf(NegotiatePrice::class);
});

test('the price tool returns the deterministic counter as json', function () {
    $product = offerPilotProductForAgent();

    $tool = new NegotiatePrice($product, null, ceilingPriceCents: 25_000);

    $payload = json_decode(
        (string) $tool->handle(new Request(['target_price_cents' => 20_000])),
        true,
    );

    expect($payload['status'])->toBe('countered')
        ->and($payload['price'])->toBe('240.00')
        ->and($payload['can_counter'])->toBeTrue();
});

test('the price tool clamps to the buyer ceiling', function () {
    $product = offerPilotProductForAgent();

    $tool = new NegotiatePrice($product, null, ceilingPriceCents: 22_000);

    $payload = json_decode(
        (string) $tool->handle(new Request(['target_price_cents' => 20_000])),
        true,
    );

    expect($payload['price'])->toBe('220.00');
});

test('the price tool never returns the merchant floor', function () {
    $product = offerPilotProductForAgent();

    $tool = new NegotiatePrice($product, null, ceilingPriceCents: 25_000);

    $json = (string) $tool->handle(new Request(['target_price_cents' => 20_000]));

    expect($json)->not->toContain('21000')
        ->and($json)->not->toContain('210.00');
});

test('the agent instructions never contain the merchant floor', function () {
    $product = offerPilotProductForAgent();

    $instructions = (string) offerPilotAgent($product)->instructions();

    expect($instructions)->toContain('Studio Headphones')
        ->and($instructions)->toContain('checks out from their cart')
        ->and($instructions)->not->toContain('product page')
        ->and($instructions)->toContain('250.00')
        ->and($instructions)->toContain('Closed-back drivers')
        ->and($instructions)->toContain('Impedance: 38 Ω')
        ->and($instructions)->not->toContain('210.00')
        ->and($instructions)->not->toContain('21000')
        ->and($instructions)->not->toContain('85.00')
        ->and($instructions)->not->toContain('8500')
        ->and($instructions)->not->toContain('floor');
});

test('the agent is told to admit when product details are unpublished', function () {
    $product = Product::factory()->create([
        'features' => [],
        'specifications' => [],
        'description' => null,
    ]);
    $instructions = (string) offerPilotAgent($product)->instructions();

    expect($instructions)
        ->toContain('Public features: not provided.')
        ->toContain('Public specifications: not provided.')
        ->toContain('If a detail is not listed, say the shop has not published it');
});

test('the final price tool counter stays above the private floor', function () {
    $product = offerPilotProductForAgent();
    $tool = new NegotiatePrice($product, null, 25_000, roundsUsed: 3);

    $payload = json_decode(
        (string) $tool->handle(new Request(['target_price_cents' => 20_000])),
        true,
    );

    expect($payload['price'])->toBe('220.00')
        ->and(json_encode($payload))->not->toContain('210.00')
        ->and(json_encode($payload))->not->toContain('21000');
});

test('the agent can be prompted without calling a provider', function () {
    $user = User::factory()->create();

    OfferPilotAgent::fake(['The merchant countered at 240.00. Do you accept?']);

    $response = offerPilotAgent(offerPilotProductForAgent())
        ->forUser($user)
        ->prompt('Can you get this to 200 for me?');

    expect((string) $response)->toContain('240.00');

    OfferPilotAgent::assertPrompted('Can you get this to 200 for me?');
});

test('the negotiation service and the tool agree on the number', function () {
    $product = offerPilotProductForAgent();

    $expected = app(NegotiationService::class)->negotiate($product, 20_000, 22_500);
    $payload = json_decode(
        (string) (new NegotiatePrice($product, null, 22_500))->handle(new Request(['target_price_cents' => 20_000])),
        true,
    );

    expect($payload['price'])->toBe($expected->formattedPrice());
});
