<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mengaktifkan atau menonaktifkan kategori. Kategori tidak pernah dihapus.
 * Kategori nonaktif tidak muncul di form lapor, tetapi laporan lamanya utuh.
 */
class SetCategoryActive
{
    public function __invoke(User $actor, Category $category, bool $active): Category
    {
        Gate::forUser($actor)->authorize('manage', Category::class);

        if (! $active && $category->is_active) {
            $otherActive = Category::query()->active()->whereKeyNot($category->id)->exists();

            if (! $otherActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Minimal harus ada satu kategori aktif agar laporan tetap bisa dibuat.',
                ]);
            }
        }

        $category->is_active = $active;
        $category->save();

        return $category;
    }
}
