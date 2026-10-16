<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Mengubah peran, status aktif, dan mereset password pengguna.
     * Tidak berlaku untuk akun sendiri, agar admin tidak bisa mengunci
     * dirinya sendiri; dengan begitu selalu ada minimal satu admin aktif.
     */
    public function manage(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->isNot($target);
    }
}
