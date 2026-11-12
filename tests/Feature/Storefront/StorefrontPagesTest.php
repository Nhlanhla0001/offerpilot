<?php

use App\Models\Merchant;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the storefront lists products with their public content', function () {
    $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Northsound DM-2 USB Desk Microphone',
        'brand' => 'Northsound',
        'category' => 'Audio',
        'features' => ['Cardioid capsule', 'USB-C connection'],
        'specifications' => ['Capsule' => 'Cardioid condenser'],
    ]);

    $this->get('/store')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('store/index')
            ->where('merchant.name', 'Northsound Audio')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Northsound DM-2 USB Desk Microphone')
            ->where('products.data.0.features.0', 'Cardioid capsule')
            ->where('products.data.0.specifications.Capsule', 'Cardioid condenser')
            ->where('assistant.merchantName', 'Northsound Audio')
            ->where('assistant.productSku', null));
});

test('the storefront paginates a seven-product catalogue into six-item pages', function () {
    $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
    Product::factory()->count(7)->for($merchant)->create();

    $this->get('/store')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('store/index')
            ->has('products.data', 6)
            ->where('products.total', 7)
            ->where('products.per_page', 6)
            ->where('products.last_page', 2));
});

test('the storefront limits an assistant result list to matching in-stock products from the current shop', function () {
    $shop = Merchant::factory()->create();
    $otherShop = Merchant::factory()->create();
    Product::factory()->for($shop)->create(['sku' => 'MATCH-A']);
    Product::factory()->for($shop)->create(['sku' => 'MATCH-B']);
    Product::factory()->for($shop)->create(['sku' => 'SIXTH-RESULT']);
    Product::factory()->for($shop)->create(['sku' => 'SOLD-OUT', 'stock_quantity' => 0]);
    Product::factory()->for($otherShop)->create(['sku' => 'FOREIGN']);

    $this->get('/store?skus=MATCH-A,MATCH-A,MATCH-B,SOLD-OUT,FOREIGN,UNKNOWN,SIXTH-RESULT')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('products.data.0.sku', 'MATCH-A')
            ->where('products.data.1.sku', 'MATCH-B')
            ->where('products.total', 2)
            ->where('filters.isAssistantResultFilter', true));
});

test('the storefront keeps a present but invalid assistant result filter empty', function (string $skus) {
    $merchant = Merchant::factory()->create();
    Product::factory()->for($merchant)->create(['sku' => 'AVAILABLE']);

    $this->get('/store?'.http_build_query(['skus' => $skus]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 0)
            ->where('products.total', 0)
            ->where('filters.isAssistantResultFilter', true));
})->with([
    'empty list' => '',
    'blank values' => ', ,',
    'malformed values' => '***,???',
]);

test('ordinary storefront browsing is not restricted to assistant result SKUs', function () {
    $merchant = Merchant::factory()->create();
    Product::factory()->count(2)->for($merchant)->create();

    $this->get('/store')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 2)
            ->where('filters.isAssistantResultFilter', false));
});

test('the storefront assistant receives every available product including products on later pages', function () {
    $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
    Product::factory()->count(6)->for($merchant)->create();
    Product::factory()->for($merchant)->create(['sku' => 'PAGE-TWO']);
    Product::factory()->for($merchant)->create([
        'sku' => 'OUT-OF-STOCK',
        'stock_quantity' => 0,
    ]);

    $this->get('/store')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('store/index')
            ->has('assistant.products', 7)
            ->where('assistant.products.6.sku', 'PAGE-TWO')
            ->missing('assistant.products.7')
            ->where('assistant.products.0.name', fn ($name) => is_string($name) && $name !== '')
            ->missing('assistant.products.0.unit_cost_cents'));
});

test('a product page exposes its features and specifications but never the private floor', function () {
    $merchant = Merchant::factory()->create(['name' => 'Northsound Audio']);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Northsound SH-1 Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
        'unit_cost_cents' => 12_500,
        'features' => ['Closed-back drivers', 'Replaceable earpads'],
        'specifications' => ['Impedance' => '38 Ω'],
    ]);

    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    $this->get('/store/HEADPHONES-PRO')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('store/show')
            ->where('product.name', 'Northsound SH-1 Studio Headphones')
            ->where('product.sku', 'HEADPHONES-PRO')
            ->where('product.features.1', 'Replaceable earpads')
            ->where('product.specifications.Impedance', '38 Ω')
            ->where('assistant.merchantName', 'Northsound Audio')
            ->where('assistant.productSku', 'HEADPHONES-PRO')
            ->missing('product.unit_cost_cents')
            ->missing('product.floor_price_cents')
            ->missing('product.rules'));
});

test('seller pages do not render the buyer assistant entry', function () {
    $user = User::factory()->create();
    Merchant::factory()->create(['user_id' => $user->id, 'name' => 'Northsound Audio']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/dashboard')
            ->missing('assistant'));
});
