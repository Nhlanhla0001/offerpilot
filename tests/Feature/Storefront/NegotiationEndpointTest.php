<?php

use App\Ai\Agents\OfferPilotAgent;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;

test('a question with no price does not surface an earlier pending quote', function () {
    $user = User::factory()->create();

    $product = Product::factory()->create(['list_price_cents' => 25_000]);
    $product->rules()->create([
        'floor_price_cents' => 21_000,
        'max_rounds' => 3,
        'allow_alternatives' => true,
    ]);

    Quote::factory()->create([
        'user_id' => $user->id,
        'product_id' => $product->id,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(10),
    ]);

    OfferPilotAgent::fake(['It ships with a cable and a carry pouch.']);

    $response = $this->actingAs($user)->postJson(
        route('store.negotiate', $product->sku),
        ['message' => 'What is included in the box?'],
    );

    $response->assertOk();
    $response->assertJsonPath('quote', null);
});
