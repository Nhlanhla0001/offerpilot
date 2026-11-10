<?php

use App\Enums\NegotiationStatus;
use App\Models\Merchant;
use App\Models\MerchantRule;
use App\Models\Product;
use App\Services\NegotiationService;

/**
 * Create a product with private merchant rules for negotiation tests.
 *
 * @param  array<string, mixed>  $productAttributes
 * @param  array<string, mixed>  $ruleAttributes
 */
function negotiableProduct(array $productAttributes = [], array $ruleAttributes = []): Product
{
    $product = Product::factory()->create([
        'list_price_cents' => 25_000,
        'stock_quantity' => 5,
        ...$productAttributes,
    ]);

    MerchantRule::factory()->create([
        'product_id' => $product->id,
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
        ...$ruleAttributes,
    ]);

    return $product->refresh();
}

test('accepts the buyer target when it clears the merchant floor', function () {
    $product = negotiableProduct();

    $result = app(NegotiationService::class)->negotiate($product, 21_000, 22_500);

    expect($result->status)->toBe(NegotiationStatus::Accepted)
        ->and($result->priceCents)->toBe(21_000)
        ->and($result->canCounter)->toBeFalse();
});

test('never charges more than the list price', function () {
    $product = negotiableProduct();

    $result = app(NegotiationService::class)->negotiate($product, 30_000, 30_000);

    expect($result->status)->toBe(NegotiationStatus::Accepted)
        ->and($result->priceCents)->toBe(25_000);
});

test('opens with a counter above the floor instead of revealing it', function () {
    $product = negotiableProduct();

    $result = app(NegotiationService::class)->negotiate($product, 20_000, 25_000);

    expect($result->status)->toBe(NegotiationStatus::Countered)
        ->and($result->priceCents)->toBe(24_000)
        ->and($result->priceCents)->not->toBe(21_000)
        ->and($result->canCounter)->toBeTrue();
});

test('walks the counter down as rounds are consumed', function () {
    $product = negotiableProduct();
    $service = app(NegotiationService::class);

    $second = $service->negotiate($product, 20_000, 25_000, roundsUsed: 1);
    $third = $service->negotiate($product, 20_000, 25_000, roundsUsed: 2);

    expect($second->priceCents)->toBe(23_000)
        ->and($third->priceCents)->toBe(22_000);
});

test('clamps the counter to the buyer ceiling', function () {
    $product = negotiableProduct();

    $result = app(NegotiationService::class)->negotiate($product, 20_000, 22_000);

    expect($result->status)->toBe(NegotiationStatus::Countered)
        ->and($result->priceCents)->toBe(22_000);
});

test('makes a best and final offer at the floor on the last round', function () {
    $product = negotiableProduct();

    $result = app(NegotiationService::class)->negotiate($product, 20_000, 25_000, roundsUsed: 3);

    expect($result->status)->toBe(NegotiationStatus::Countered)
        ->and($result->priceCents)->toBe(21_000)
        ->and($result->canCounter)->toBeFalse();
});

test('rejects a ceiling below the floor and offers in-budget alternatives', function () {
    $product = negotiableProduct();

    $alternative = Product::factory()->create([
        'merchant_id' => $product->merchant_id,
        'name' => 'Wireless Earbuds',
        'list_price_cents' => 17_500,
        'stock_quantity' => 3,
    ]);

    $result = app(NegotiationService::class)->negotiate($product, 15_000, 19_000);

    expect($result->status)->toBe(NegotiationStatus::Rejected)
        ->and($result->priceCents)->toBeNull()
        ->and($result->alternatives)->toHaveCount(1)
        ->and($result->alternatives[0]['id'])->toBe($alternative->id);
});

test('does not offer alternatives when the merchant disallows them', function () {
    $product = negotiableProduct([], ['allow_alternatives' => false]);

    Product::factory()->create([
        'merchant_id' => $product->merchant_id,
        'list_price_cents' => 15_000,
    ]);

    $result = app(NegotiationService::class)->negotiate($product, 12_000, 16_000);

    expect($result->status)->toBe(NegotiationStatus::Rejected)
        ->and($result->alternatives)->toBe([]);
});

test('keeps alternatives to in-stock products from the same merchant', function () {
    $product = negotiableProduct();

    $outOfStock = Product::factory()->create([
        'merchant_id' => $product->merchant_id,
        'list_price_cents' => 12_000,
        'stock_quantity' => 0,
    ]);

    $otherMerchant = Product::factory()->create([
        'merchant_id' => Merchant::factory()->create()->id,
        'list_price_cents' => 11_000,
        'stock_quantity' => 5,
    ]);

    $result = app(NegotiationService::class)->negotiate($product, 10_000, 14_000);

    expect($result->alternatives)->toBe([])
        ->and($result->alternatives)->not->toContain($outOfStock->id)
        ->and($result->alternatives)->not->toContain($otherMerchant->id);
});

test('rejects a mandate whose ceiling is below its target', function () {
    $product = negotiableProduct();

    expect(fn () => app(NegotiationService::class)->negotiate($product, 22_000, 21_000))
        ->toThrow(InvalidArgumentException::class);
});

test('never exposes the merchant floor in the payload handed to the model', function () {
    $product = negotiableProduct();

    $payload = app(NegotiationService::class)->negotiate($product, 20_000, 25_000)->toArray();
    $encoded = json_encode($payload);

    expect($encoded)->not->toContain('21000')
        ->and($encoded)->not->toContain('210.00')
        ->and($payload)->not->toHaveKey('floor_price_cents');
});
