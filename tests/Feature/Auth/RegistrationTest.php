<?php

use App\Enums\UserRole;
use App\Models\User;

test('registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('new users can register with their unit', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'unit' => 'Keuangan',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->unit)->toBe('Keuangan')
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->is_active)->toBeTrue();
});

test('registration requires a unit', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('unit');

    $this->assertGuest();
});

test('users cannot choose their own role or status during registration', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'unit' => 'Keuangan',
        'role' => 'admin',
        'is_active' => false,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::User)
        ->and($user->is_active)->toBeTrue();
});
