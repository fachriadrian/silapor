<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mengubah peran pengguna antara User dan Admin (ADM-04).
 */
class ChangeUserRole
{
    public function __invoke(User $actor, User $target, UserRole $role): User
    {
        Gate::forUser($actor)->authorize('manage', $target);

        // 'role' tidak mass-assignable, jadi diatur eksplisit.
        $target->forceFill(['role' => $role])->save();

        return $target;
    }
}
