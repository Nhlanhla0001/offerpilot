<?php

use App\Models\Merchant;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Build a shop with one product and return its seller and product.
 *
 * @return array{0: User, 1: Product}
 */
function shopWithProduct(): array
{
    $seller = User::factory()->create();
    $merchant = Merchant::factory()->create(['user_id' => $seller->id]);
    $product = Product::factory()->create([
        'merchant_id' => $merchant->id,
        'name' => 'Studio Headphones',
        'sku' => 'HEADPHONES-PRO',
    ]);

    return [$seller, $product];
}

test('the seller sees every offer on their own shop, including guest offers', function () {
    [$seller, $product] = shopWithProduct();

    Quote::factory()->create([
        'product_id' => $product->id,
        'guest_token' => 'guest-session',
        'target_price_cents' => 20_000,
        'agreed_price_cents' => 23_000,
        'rounds_used' => 2,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    Quote::factory()->create([
        'product_id' => $product->id,
        'user_id' => User::factory()->create(['name' => 'Thandi Buyer'])->id,
        'target_price_cents' => 25_000,
        'agreed_price_cents' => 25_000,
        'status' => 'fulfilled',
    ]);

    // Another merchant's offer must never appear.
    Quote::factory()->create(['status' => 'pending', 'expires_at' => now()->addMinutes(10)]);

    $this->actingAs($seller)
        ->get('/offers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('offers/index')
            ->has('offers', 2)
            ->where('offers.0.buyer', 'Thandi Buyer')
            ->where('offers.0.status', 'Paid')
            ->where('offers.1.buyer', null)
            ->where('offers.1.status', 'Open')
            ->where('offers.1.held', '230.00')
            ->where('counts.all', 2)
            ->where('counts.open', 1)
            ->where('counts.paid', 1));
});

test('offers can be filtered by status', function () {
    [$seller, $product] = shopWithProduct();

    Quote::factory()->create([
        'product_id' => $product->id,
        'status' => 'fulfilled',
    ]);

    Quote::factory()->expired()->create([
        'product_id' => $product->id,
    ]);

    $this->actingAs($seller)
        ->get('/offers?status=paid')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.status', 'Paid')
            ->where('filters.status', 'paid'));

    $this->actingAs($seller)
        ->get('/offers?status=expired')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('offers', 1)
            ->where('offers.0.status', 'Expired'));
});

test('a shopper without a shop cannot open the offers screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/offers')
        ->assertRedirect('/store');
});
