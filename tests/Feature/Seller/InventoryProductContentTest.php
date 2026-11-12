<?php

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function inventoryProductContentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Northsound DM-2 USB Desk Microphone',
        'sku' => 'MIC-CONTENT-TEST',
        'brand' => 'Northsound',
        'category' => 'Audio',
        'type' => 'physical',
        'description' => 'A USB microphone for a desk setup.',
        'list_price' => '120.00',
        'floor_price' => '80.00',
        'unit_cost' => '46.25',
        'stock_quantity' => 5,
        'image_path' => 'demo/audio.svg',
        'negotiation_enabled' => true,
        'max_rounds' => 3,
        'features' => ['Cardioid capsule', 'USB-C connection'],
        'specifications' => [
            ['key' => 'Capsule', 'value' => 'Cardioid condenser'],
            ['key' => 'Connection', 'value' => 'USB-C'],
        ],
    ], $overrides);
}

test('seller can create and edit product features and specifications', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);

    $this->actingAs($seller)
        ->post('/inventory', inventoryProductContentPayload())
        ->assertRedirect('/inventory');

    $product = Product::query()->where('sku', 'MIC-CONTENT-TEST')->firstOrFail();

    expect($product->features)->toBe(['Cardioid capsule', 'USB-C connection'])
        ->and($product->specifications)->toBe([
            'Capsule' => 'Cardioid condenser',
            'Connection' => 'USB-C',
        ]);

    $updatedPayload = inventoryProductContentPayload([
        'features' => ['Broadcast-ready sound'],
        'specifications' => [
            ['key' => 'Pattern', 'value' => 'Cardioid'],
        ],
    ]);

    $this->actingAs($seller)
        ->patch("/inventory/{$product->id}", $updatedPayload)
        ->assertRedirect('/inventory');

    expect($product->refresh()->features)->toBe(['Broadcast-ready sound'])
        ->and($product->specifications)->toBe(['Pattern' => 'Cardioid']);

    $this->actingAs($seller)
        ->get("/inventory/{$product->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/form')
            ->where('product.features.0', 'Broadcast-ready sound')
            ->where('product.specifications.0.key', 'Pattern')
            ->where('product.specifications.0.value', 'Cardioid'));

    expect($merchant->id)->toBe($product->merchant_id);
});

test('seller uploads are stored publicly and exposed as buyer product image urls', function () {
    Storage::fake('public');
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);

    $this->actingAs($seller)
        ->post('/inventory', inventoryProductContentPayload([
            'image' => UploadedFile::fake()->image('desk-microphone.jpg'),
        ]))
        ->assertRedirect('/inventory');

    $product = Product::query()->where('sku', 'MIC-CONTENT-TEST')->firstOrFail();
    Storage::disk('public')->assertExists($product->image_path);

    $this->get('/store/'.$product->sku)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('product.image', Storage::disk('public')->url($product->image_path))
            ->missing('product.unit_cost_cents')
            ->missing('product.floor_price_cents'));
});

test('seller can clear product features and specifications', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->for($merchant)->create([
        'features' => ['Removable earpads'],
        'specifications' => ['Impedance' => '38 Ω'],
    ]);

    $this->actingAs($seller)
        ->patch("/inventory/{$product->id}", inventoryProductContentPayload([
            'sku' => $product->sku,
            'features' => [],
            'specifications' => [],
        ]))
        ->assertRedirect('/inventory');

    expect($product->refresh()->features)->toBe([])
        ->and($product->specifications)->toBe([]);
});

test('seller content fields reject invalid and oversized entries', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->for($merchant)->create([
        'features' => ['Existing feature'],
        'specifications' => ['Existing spec' => 'Existing value'],
    ]);
    $tooManyFeatures = array_fill(0, 21, 'Feature');

    $this->actingAs($seller)
        ->from("/inventory/{$product->id}/edit")
        ->patch("/inventory/{$product->id}", inventoryProductContentPayload([
            'sku' => $product->sku,
            'features' => $tooManyFeatures,
            'specifications' => [
                ['key' => '', 'value' => 'Value'],
                ['key' => 'Long value', 'value' => str_repeat('x', 201)],
            ],
        ]))
        ->assertSessionHasErrors([
            'features',
            'specifications.0.key',
            'specifications.1.value',
        ]);

    expect($product->refresh()->features)->toBe(['Existing feature'])
        ->and($product->specifications)->toBe(['Existing spec' => 'Existing value']);
});

test('seller cannot submit duplicate specification names', function () {
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->for($merchant)->create([
        'specifications' => ['Existing spec' => 'Existing value'],
    ]);

    $this->actingAs($seller)
        ->from("/inventory/{$product->id}/edit")
        ->patch("/inventory/{$product->id}", inventoryProductContentPayload([
            'sku' => $product->sku,
            'specifications' => [
                ['key' => 'Pattern', 'value' => 'Cardioid'],
                ['key' => ' pattern ', 'value' => 'Omnidirectional'],
            ],
        ]))
        ->assertSessionHasErrors(['specifications.1.key']);

    expect($product->refresh()->specifications)->toBe([
        'Existing spec' => 'Existing value',
    ]);
});

test('seller cannot update another merchant product content', function () {
    $owner = User::factory()->create();
    $otherSeller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $owner->id]);
    $otherMerchant = Merchant::factory()->create(['user_id' => $otherSeller->id]);
    $product = Product::factory()->for($merchant)->create([
        'features' => ['Owner feature'],
        'specifications' => ['Owner spec' => 'Owner value'],
    ]);

    $this->actingAs($otherSeller)
        ->patch("/inventory/{$product->id}", inventoryProductContentPayload([
            'sku' => $product->sku,
            'features' => ['Unauthorized change'],
        ]))
        ->assertNotFound();

    expect($product->refresh()->merchant_id)->toBe($merchant->id)
        ->and($product->features)->toBe(['Owner feature'])
        ->and($product->specifications)->toBe(['Owner spec' => 'Owner value'])
        ->and($otherMerchant->id)->not->toBe($merchant->id);
});
