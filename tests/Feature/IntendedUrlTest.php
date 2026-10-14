<?php

use App\Enums\UserRole;
use App\Models\User;

test('a regular user is sent to the dashboard after login even if they first opened the admin area', function () {
    $user = User::factory()->create();

    $this->get('/admin')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('a new user is sent to the dashboard after registering even if they first opened the admin area', function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'unit' => 'Keuangan',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));
});

test('an admin is still sent to the admin area after login', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    $this->get('/admin')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard', absolute: false));
});
