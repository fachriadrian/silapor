<?php

use App\Enums\UserRole;
use App\Models\User;

function createAdminUser(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('guests are redirected to login when opening the admin area', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('regular users cannot open the admin area', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admins can open the admin area', function () {
    $this->actingAs(createAdminUser())
        ->get('/admin')
        ->assertOk();
});

test('admins are redirected from the user dashboard to the admin dashboard', function () {
    $this->actingAs(createAdminUser())
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('regular users stay on the user dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});

test('deactivated users are logged out on their next request', function () {
    $user = User::factory()->create();
    $user->forceFill(['is_active' => false])->save();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
