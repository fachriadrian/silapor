<?php

use App\Actions\Users\AdminResetUserPassword;
use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\SetUserActive;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

function manageUsersAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('an admin can promote a user to admin and demote them again', function () {
    $admin = manageUsersAdmin();
    $user = User::factory()->create();

    app(ChangeUserRole::class)($admin, $user, UserRole::Admin);

    expect($user->refresh()->role)->toBe(UserRole::Admin);

    app(ChangeUserRole::class)($admin, $user, UserRole::User);

    expect($user->refresh()->role)->toBe(UserRole::User);
});

test('an admin cannot change their own role or status', function () {
    $admin = manageUsersAdmin();

    expect(fn () => app(ChangeUserRole::class)($admin, $admin, UserRole::User))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(SetUserActive::class)($admin, $admin, false))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(AdminResetUserPassword::class)($admin, $admin))
        ->toThrow(AuthorizationException::class);

    expect($admin->refresh()->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue();
});

test('a regular user cannot manage other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect(fn () => app(ChangeUserRole::class)($user, $other, UserRole::Admin))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(SetUserActive::class)($user, $other, false))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(AdminResetUserPassword::class)($user, $other))
        ->toThrow(AuthorizationException::class);
});

test('an admin can deactivate and reactivate an account', function () {
    $admin = manageUsersAdmin();
    $user = User::factory()->create();

    app(SetUserActive::class)($admin, $user, false);

    expect($user->refresh()->is_active)->toBeFalse();

    app(SetUserActive::class)($admin, $user, true);

    expect($user->refresh()->is_active)->toBeTrue();
});

test('resetting a password returns a temporary one and the old one stops working', function () {
    $admin = manageUsersAdmin();
    $user = User::factory()->create();
    $oldHash = $user->password;
    $oldRememberToken = $user->remember_token;

    $password = app(AdminResetUserPassword::class)($admin, $user);

    $user->refresh();

    expect($password)->toBeString()
        ->and(strlen($password))->toBe(12)
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->password)->not->toBe($oldHash)
        ->and($user->password)->not->toBe($password)
        ->and($user->remember_token)->not->toBe($oldRememberToken);
});

test('each reset produces a different temporary password', function () {
    $admin = manageUsersAdmin();
    $user = User::factory()->create();

    $first = app(AdminResetUserPassword::class)($admin, $user);
    $second = app(AdminResetUserPassword::class)($admin, $user);

    expect($first)->not->toBe($second);
});

test('resetting a password ends the existing database sessions of that user', function () {
    if (! Schema::hasTable('sessions')) {
        $this->markTestSkipped('Tabel sessions tidak tersedia.');
    }

    config(['session.driver' => 'database']);

    $admin = manageUsersAdmin();
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach ([['aaa', $user->id], ['bbb', $other->id]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
    }

    app(AdminResetUserPassword::class)($admin, $user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});
