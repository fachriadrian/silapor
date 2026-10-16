<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Mengaktifkan atau menonaktifkan akun (ADM-03). Akun nonaktif langsung
 * dikeluarkan pada permintaan berikutnya oleh middleware EnsureUserIsActive.
 * Laporan milik akun itu tetap utuh.
 */
class SetUserActive
{
    public function __invoke(User $actor, User $target, bool $active): User
    {
        Gate::forUser($actor)->authorize('manage', $target);

        $target->forceFill(['is_active' => $active])->save();

        return $target;
    }
}
