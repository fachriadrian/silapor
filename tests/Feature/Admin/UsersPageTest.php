<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function usersPageAdmin(): User
{
    $admin = User::factory()->create(['name' => 'Staf IT Utama']);
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('only admins can open the users page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs(usersPageAdmin())
        ->get(route('admin.users.index'))
        ->assertOk();
});

test('the list can be searched and filtered', function () {
    User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@kantor.test', 'unit' => 'Keuangan']);
    User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@kantor.test', 'unit' => 'HRD', 'is_active' => false]);

    $this->actingAs(usersPageAdmin());

    Livewire::test('pages::admin.users')
        ->set('search', 'budi@kantor')
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah')
        ->set('search', 'hrd')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Budi Santoso')
        ->set('search', '')
        ->set('status', 'nonaktif')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Budi Santoso')
        ->set('status', '')
        ->set('role', 'admin')
        ->assertSee('Staf IT Utama')
        ->assertDontSee('Budi Santoso');
});

test('an admin can deactivate and reactivate a user from the page', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs(usersPageAdmin());

    Livewire::test('pages::admin.users')
        ->call('toggleActive', $user->id)
        ->assertSee('dinonaktifkan');

    expect($user->refresh()->is_active)->toBeFalse();

    Livewire::test('pages::admin.users')
        ->call('toggleActive', $user->id)
        ->assertSee('diaktifkan');

    expect($user->refresh()->is_active)->toBeTrue();
});

test('an admin can change a role from the page', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs(usersPageAdmin());

    Livewire::test('pages::admin.users')
        ->call('toggleRole', $user->id)
        ->assertSee('Budi Santoso sekarang berperan');

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});

test('resetting a password shows the temporary password once', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs(usersPageAdmin());

    $component = Livewire::test('pages::admin.users')
        ->call('resetPassword', $user->id)
        ->assertSee('Password sementara untuk Budi Santoso');

    $password = $component->instance()->temporaryPassword;

    expect($password)->toBeString()
        ->and(Hash::check($password, $user->refresh()->password))->toBeTrue();

    $component->call('dismissPassword')
        ->assertSet('temporaryPassword', null)
        ->assertDontSee('Password sementara untuk');
});

test('an admin cannot manage their own account from the page', function () {
    $admin = usersPageAdmin();

    $this->actingAs($admin);

    Livewire::test('pages::admin.users')
        ->call('toggleActive', $admin->id)
        ->assertForbidden();

    Livewire::test('pages::admin.users')
        ->call('toggleRole', $admin->id)
        ->assertForbidden();

    Livewire::test('pages::admin.users')
        ->call('resetPassword', $admin->id)
        ->assertForbidden();

    expect($admin->refresh()->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue();
});

test('the page actions refuse regular users even when called directly', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::admin.users')
        ->call('toggleRole', $other->id)
        ->assertForbidden();

    expect($other->refresh()->role)->toBe(UserRole::User);
});
