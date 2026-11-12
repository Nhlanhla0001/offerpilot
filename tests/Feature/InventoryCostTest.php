<?php

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function inventoryCostTestSeller(): array
{
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Desk microphone',
        'sku' => 'MIC-COST-TEST',
    ]);
    $product->rules()->create([
        'floor_price_cents' => 8_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    return [$seller, $product];
}

function inventoryCostTestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Desk microphone',
        'sku' => 'MIC-COST-TEST',
        'brand' => 'Northsound',
        'category' => 'Audio',
        'type' => 'physical',
        'description' => 'USB microphone for a desk setup.',
        'list_price' => '120.00',
        'floor_price' => '80.00',
        'unit_cost' => '46.25',
        'stock_quantity' => 5,
        'image_path' => 'demo/audio.svg',
        'negotiation_enabled' => true,
        'max_rounds' => 3,
    ], $overrides);
}

test('the seller saves product cost in cents and can leave it blank', function () {
    [$seller, $product] = inventoryCostTestSeller();

    $this->actingAs($seller)
        ->from('/inventory')
        ->patch("/inventory/{$product->id}", inventoryCostTestPayload())
        ->assertRedirect('/inventory');

    expect($product->refresh()->unit_cost_cents)->toBe(4_625);

    $this->actingAs($seller)
        ->from('/inventory')
        ->patch("/inventory/{$product->id}", inventoryCostTestPayload(['unit_cost' => null]))
        ->assertRedirect('/inventory');

    expect($product->refresh()->unit_cost_cents)->toBeNull();
});

test('the seller receives a validation error for a negative product cost', function () {
    [$seller, $product] = inventoryCostTestSeller();

    $this->actingAs($seller)
        ->from('/inventory')
        ->patch("/inventory/{$product->id}", inventoryCostTestPayload(['unit_cost' => '-0.01']))
        ->assertSessionHasErrors('unit_cost');
});

test('the seller edit form shows the saved product cost', function () {
    [$seller, $product] = inventoryCostTestSeller();

    $this->actingAs($seller)
        ->get("/inventory/{$product->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/form')
            ->where('product.unit_cost', null));
});
