<?php

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('refuses to create a paypal order when the quote exceeds the buyer ceiling', function () {
    Http::fake();

    $user = User::factory()->create();

    $quote = Quote::factory()->create([
        'user_id' => $user->id,
        'agreed_price_cents' => 22_500,
        'ceiling_price_cents' => 20_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('store.checkout', $quote->product->sku),
        ['quote' => $quote->quote_id],
    );

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'This offer is above the maximum you authorised, so it cannot be charged.');

    Http::assertNothingSent();
});

test('refuses to create a paypal order for an expired quote', function () {
    Http::fake();

    $user = User::factory()->create();

    $quote = Quote::factory()->expired()->create([
        'user_id' => $user->id,
        'ceiling_price_cents' => 25_000,
    ]);

    $response = $this->actingAs($user)->postJson(
        route('store.checkout', $quote->product->sku),
        ['quote' => $quote->quote_id],
    );

    $response->assertStatus(422);

    Http::assertNothingSent();
});

test('does not let a buyer check out another buyers quote', function () {
    Http::fake();

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $quote = Quote::factory()->create([
        'user_id' => $owner->id,
        'ceiling_price_cents' => 25_000,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);

    $response = $this->actingAs($other)->postJson(
        route('store.checkout', $quote->product->sku),
        ['quote' => $quote->quote_id],
    );

    $response->assertNotFound();

    Http::assertNothingSent();
});
