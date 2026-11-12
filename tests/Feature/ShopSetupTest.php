<?php

use App\Models\Merchant;
use App\Models\User;

test('a seller cannot create a second shop through the setup endpoint', function () {
    $seller = User::factory()->create();
    Merchant::factory()->create(['user_id' => $seller->id]);

    $this->actingAs($seller)
        ->post('/inventory/setup', [
            'name' => 'Second Shop',
            'email' => 'second-shop@example.com',
        ])
        ->assertConflict();

    expect(Merchant::query()->where('user_id', $seller->id)->count())->toBe(1);
});
