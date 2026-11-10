<?php

test('sends visitors from the root to the shop', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('store.index'));
});
