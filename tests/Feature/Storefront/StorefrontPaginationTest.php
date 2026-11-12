<?php

use App\Models\Merchant;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

test('storefront pagination preserves search and category filters', function () {
    $merchant = Merchant::factory()->create();
    Product::factory()->count(12)->create([
        'merchant_id' => $merchant->id,
        'category' => 'Audio',
    ]);
    Product::factory()->create([
        'merchant_id' => $merchant->id,
        'category' => 'Computing',
    ]);

    $this->get('/store?q=Audio&category=Audio&page=2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('store/index')
            ->has('products.data', 6)
            ->where('products.current_page', 2)
            ->where('products.total', 12)
            ->where('filters.q', 'Audio')
            ->where('filters.category', 'Audio'));
});

test('storefront catalog and category filters contain only the displayed shop products', function () {
    $storeMerchant = Merchant::factory()->create(['name' => 'First Shop']);
    Product::factory()->create([
        'merchant_id' => $storeMerchant->id,
        'category' => 'Audio',
        'name' => 'First Shop Microphone',
    ]);

    $otherMerchant = Merchant::factory()->create(['name' => 'Other Shop']);
    Product::factory()->create([
        'merchant_id' => $otherMerchant->id,
        'category' => 'Restricted Category',
        'name' => 'Other Shop Product',
    ]);

    $this->get('/store')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('merchant.name', 'First Shop')
            ->has('products.data', 1)
            ->where('products.total', 1)
            ->where('products.data.0.name', 'First Shop Microphone')
            ->where('categories', ['Audio']));
});
