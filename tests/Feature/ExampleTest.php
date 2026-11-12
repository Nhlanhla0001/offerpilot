<?php

use Inertia\Testing\AssertableInertia as Assert;

test('introduces the product before sending visitors to the shop', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('role', 'guest'));
});
