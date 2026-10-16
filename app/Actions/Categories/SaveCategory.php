<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Membuat kategori baru atau mengubah kategori yang ada (ADM-01).
 *
 * - Prefix: 2-4 huruf kapital, unik, dan terkunci setelah dipakai laporan
 *   karena sudah menjadi bagian dari kode laporan yang beredar (KODE-05).
 * - Nama boleh diubah kapan saja; laporan lama merujuk lewat id sehingga
 *   hanya tampilannya yang berubah.
 */
class SaveCategory
{
    public function __invoke(User $actor, ?Category $category, string $name, string $prefix): Category
    {
        Gate::forUser($actor)->authorize('manage', Category::class);

        $name = Str::squish($name);
        $prefix = strtoupper(trim($prefix));

        Validator::make(
            ['name' => $name, 'prefix' => $prefix],
            [
                'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category?->id)],
                'prefix' => ['required', 'regex:/^[A-Z]{2,4}$/', Rule::unique('categories', 'prefix')->ignore($category?->id)],
            ],
            ['prefix.regex' => 'Prefix harus 2–4 huruf kapital (A–Z), tanpa angka atau spasi.'],
        )->validate();

        if ($category === null) {
            return Category::create(['name' => $name, 'prefix' => $prefix]);
        }

        if ($prefix !== $category->prefix && $category->reports()->exists()) {
            throw ValidationException::withMessages([
                'prefix' => 'Prefix tidak dapat diubah karena sudah dipakai oleh laporan.',
            ]);
        }

        $category->update(['name' => $name, 'prefix' => $prefix]);

        return $category;
    }
}
