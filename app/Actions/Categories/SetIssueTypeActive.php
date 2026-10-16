<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\IssueType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetIssueTypeActive
{
    public function __invoke(User $actor, IssueType $issueType, bool $active): IssueType
    {
        Gate::forUser($actor)->authorize('manage', Category::class);

        $issueType->is_active = $active;
        $issueType->save();

        return $issueType;
    }
}
