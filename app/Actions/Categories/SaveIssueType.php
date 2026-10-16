<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\IssueType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Membuat atau mengubah jenis kendala (sub-jenis) di dalam sebuah kategori
 * (ADM-02). Nama harus unik di dalam kategori yang sama.
 */
class SaveIssueType
{
    public function __invoke(User $actor, Category $category, ?IssueType $issueType, string $name): IssueType
    {
        Gate::forUser($actor)->authorize('manage', Category::class);

        if ($issueType !== null && $issueType->category_id !== $category->id) {
            throw new InvalidArgumentException('Jenis kendala ini bukan milik kategori tersebut.');
        }

        $name = Str::squish($name);

        Validator::make(
            ['name' => $name],
            [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    Rule::unique('issue_types', 'name')
                        ->where('category_id', $category->id)
                        ->ignore($issueType?->id),
                ],
            ],
        )->validate();

        if ($issueType === null) {
            return IssueType::create(['category_id' => $category->id, 'name' => $name]);
        }

        $issueType->update(['name' => $name]);

        return $issueType;
    }
}
