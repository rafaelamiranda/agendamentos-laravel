<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard sends authenticated users to the agenda', function () {
    $this->actingAs(User::factory()->administrador()->create());

    $this->get(route('dashboard'))->assertRedirect('/agenda');
});
