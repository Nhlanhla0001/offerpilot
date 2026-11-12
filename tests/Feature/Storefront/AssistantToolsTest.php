<?php

use App\Ai\Agents\StorefrontAssistant;
use App\Ai\Tools\GetStoreProduct;
use App\Ai\Tools\NegotiateStorePrice;
use App\Ai\Tools\ProposeAddToCart;
use App\Ai\Tools\SearchStoreProducts;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;

/**
 * Create the standalone storefront merchant and return it.
 */
function assistantShop(): Merchant
{
    return Merchant::factory()->create(['name' => 'Northsound Audio']);
}

test('the search tool returns public facts for matching products in the shop', function () {
    $merchant = assistantShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Northsound DM-2 USB Desk Microphone',
        'brand' => 'Northsound',
        'category' => 'Audio',
        'sku' => 'MIC-DM2',
        'unit_cost_cents' => 8_500,
        'features' => ['Cardioid capsule', 'USB-C connection'],
        'specifications' => ['Capsule' => 'Cardioid condenser'],
        'list_price_cents' => 18_900,
    ]);
    $product->rules()->create([
        'floor_price_cents' => 15_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $json = (string) (new SearchStoreProducts($merchant))->handle(new Request(['query' => 'microphone']));
    $payload = json_decode($json, true);

    expect($payload['products'])->toHaveCount(1)
        ->and($payload['products'][0]['sku'])->toBe('MIC-DM2')
        ->and($payload['products'][0]['name'])->toBe('Northsound DM-2 USB Desk Microphone')
        ->and($payload['products'][0]['features'])->toContain('Cardioid capsule')
        ->and($payload['products'][0]['specifications'])->toHaveKey('Capsule', 'Cardioid condenser')
        ->and($json)->not->toContain('unit_cost_cents')
        ->and($json)->not->toContain('floor_price_cents')
        ->and($json)->not->toContain('8500')
        ->and($json)->not->toContain('15000');
});

test('the search tool never leaves the selected merchant', function () {
    $merchant = assistantShop();
    $other = Merchant::factory()->create();

    Product::factory()->create(['merchant_id' => $merchant->id, 'name' => 'Studio Microphone', 'sku' => 'MINE-1']);
    Product::factory()->create(['merchant_id' => $other->id, 'name' => 'Studio Microphone', 'sku' => 'THEIRS-1']);

    $payload = json_decode(
        (string) (new SearchStoreProducts($merchant))->handle(new Request(['query' => 'microphone'])),
        true,
    );

    expect(collect($payload['products'])->pluck('sku')->all())->toBe(['MINE-1']);
});

test('the search tool reports no products when nothing matches', function () {
    $merchant = assistantShop();
    Product::factory()->create(['merchant_id' => $merchant->id, 'name' => 'Headphones']);

    $payload = json_decode(
        (string) (new SearchStoreProducts($merchant))->handle(new Request(['query' => 'zzz-no-match'])),
        true,
    );

    expect($payload['products'])->toBe([]);
});

test('the product tool returns public facts for a known sku', function () {
    $merchant = assistantShop();
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

    $json = (string) (new GetStoreProduct($merchant))->handle(new Request(['sku' => 'HEADPHONES-PRO']));
    $payload = json_decode($json, true);

    expect($payload['product']['sku'])->toBe('HEADPHONES-PRO')
        ->and($payload['product']['name'])->toBe('Studio Headphones')
        ->and($payload['product']['price'])->toBe('250.00')
        ->and($json)->not->toContain('unit_cost_cents')
        ->and($json)->not->toContain('floor_price_cents')
        ->and($json)->not->toContain('12500')
        ->and($json)->not->toContain('21000');
});

test('the product tool states an unknown sku as unknown', function () {
    $merchant = assistantShop();

    $payload = json_decode(
        (string) (new GetStoreProduct($merchant))->handle(new Request(['sku' => 'NOPE'])),
        true,
    );

    expect($payload['product'])->toBeNull()
        ->and($payload['message'])->toContain('not');
});

test('the product tool treats an unavailable sku as not available', function () {
    $merchant = assistantShop();
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Out of Stock Headphones',
        'sku' => 'OOS-1',
        'stock_quantity' => 0,
    ]);

    $payload = json_decode(
        (string) (new GetStoreProduct($merchant))->handle(new Request(['sku' => 'OOS-1'])),
        true,
    );

    expect($payload['product'])->toBeNull()
        ->and($payload['message'])->toContain('not');
});

test('neither tool schema exposes seller floor or cost', function () {
    $merchant = assistantShop();
    $factory = new JsonSchemaTypeFactory;

    $searchSchema = (new SearchStoreProducts($merchant))->schema($factory);
    $getSchema = (new GetStoreProduct($merchant))->schema($factory);

    expect(array_keys($searchSchema))->toBe(['query'])
        ->and(array_keys($getSchema))->toBe(['sku']);
});

test('the storefront assistant instructions never reveal floor or cost', function () {
    $merchant = assistantShop();
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
        'unit_cost_cents' => 8_500,
        'list_price_cents' => 25_000,
    ]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $instructions = (string) (new StorefrontAssistant($merchant, $product))->instructions();

    expect($instructions)->toContain('Studio Headphones')
        ->and($instructions)->toContain('cart')
        ->and($instructions)->toContain('checks out')
        ->and($instructions)->not->toContain('product page')
        ->and($instructions)->not->toContain('8500')
        ->and($instructions)->not->toContain('21000')
        ->and($instructions)->not->toContain('floor')
        ->and($instructions)->not->toContain('cost');
});

test('the storefront assistant registers only merchant-scoped catalogue and negotiation tools', function () {
    $merchant = assistantShop();
    $product = Product::factory()->create(['merchant_id' => $merchant->id]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $tools = iterator_to_array((new StorefrontAssistant($merchant, $product))->tools());

    expect($tools)->toHaveCount(4)
        ->and($tools[0])->toBeInstanceOf(SearchStoreProducts::class)
        ->and($tools[1])->toBeInstanceOf(GetStoreProduct::class)
        ->and($tools[2])->toBeInstanceOf(NegotiateStorePrice::class)
        ->and($tools[3])->toBeInstanceOf(ProposeAddToCart::class);
});
