<?php

use App\Models\User;

test('the removed starter dashboard is not exposed to guests', function () {
    $this->get('/dashboard')->assertNotFound();
});

test('the removed starter dashboard is not exposed to authenticated users', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/dashboard')->assertNotFound();
});
