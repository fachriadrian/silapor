<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pengelolaan kategori dan jenis kendala hanya untuk Staf IT.
 * Dipakai lewat: Gate::authorize('manage', Category::class).
 */
class CategoryPolicy
{
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
