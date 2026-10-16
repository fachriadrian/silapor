<?php

use App\Actions\Categories\SaveCategory;
use App\Actions\Categories\SaveIssueType;
use App\Actions\Categories\SetCategoryActive;
use App\Actions\Categories\SetIssueTypeActive;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function categoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('an admin can create a category, tidy the name and store the prefix in uppercase', function () {
    $category = app(SaveCategory::class)(categoryAdmin(), null, '  Perangkat   Audio ', 'pra');

    expect($category->name)->toBe('Perangkat Audio')
        ->and($category->prefix)->toBe('PRA')
        ->and($category->refresh()->is_active)->toBeTrue();
});

test('prefixes must be two to four capital letters', function () {
    $admin = categoryAdmin();

    foreach (['A', 'ABCDE', 'W1', 'W F', ''] as $prefix) {
        expect(fn () => app(SaveCategory::class)($admin, null, 'Nama '.$prefix, $prefix))
            ->toThrow(ValidationException::class);
    }

    expect(Category::count())->toBe(0);
});

test('names and prefixes must be unique', function () {
    $admin = categoryAdmin();
    Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);

    expect(fn () => app(SaveCategory::class)($admin, null, 'Jaringan WiFi', 'JW'))
        ->toThrow(ValidationException::class);

    expect(fn () => app(SaveCategory::class)($admin, null, 'Nirkabel', 'wf'))
        ->toThrow(ValidationException::class);

    expect(Category::count())->toBe(1);
});

test('a regular user cannot manage categories', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    expect(fn () => app(SaveCategory::class)($user, null, 'Baru', 'BRU'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(SetCategoryActive::class)($user, $category, false))
        ->toThrow(AuthorizationException::class);
});

test('the prefix is locked once reports use it, but the name can still change', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);
    Report::factory()->for($category)->create();

    expect(fn () => app(SaveCategory::class)($admin, $category, 'Jaringan WiFi', 'WIFI'))
        ->toThrow(ValidationException::class);

    $renamed = app(SaveCategory::class)($admin, $category, 'WiFi Kantor', 'WF');

    expect($renamed->refresh()->name)->toBe('WiFi Kantor')
        ->and($renamed->prefix)->toBe('WF');
});

test('the prefix can change while no report uses it', function () {
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);

    $updated = app(SaveCategory::class)(categoryAdmin(), $category, 'Jaringan WiFi', 'WIFI');

    expect($updated->refresh()->prefix)->toBe('WIFI');
});

test('the last active category cannot be deactivated', function () {
    $admin = categoryAdmin();
    $only = Category::factory()->create();

    expect(fn () => app(SetCategoryActive::class)($admin, $only, false))
        ->toThrow(ValidationException::class);

    expect($only->refresh()->is_active)->toBeTrue();
});

test('a category can be deactivated and activated again when another one stays active', function () {
    $admin = categoryAdmin();
    $first = Category::factory()->create();
    Category::factory()->create();

    app(SetCategoryActive::class)($admin, $first, false);

    expect($first->refresh()->is_active)->toBeFalse()
        ->and(Category::active()->count())->toBe(1);

    app(SetCategoryActive::class)($admin, $first, true);

    expect($first->refresh()->is_active)->toBeTrue();
});

test('issue types are created inside a category with unique names per category', function () {
    $admin = categoryAdmin();
    $wifi = Category::factory()->create();
    $printer = Category::factory()->create();

    $type = app(SaveIssueType::class)($admin, $wifi, null, '  Putus-putus ');

    expect($type->name)->toBe('Putus-putus')
        ->and($type->category_id)->toBe($wifi->id);

    expect(fn () => app(SaveIssueType::class)($admin, $wifi, null, 'Putus-putus'))
        ->toThrow(ValidationException::class);

    // Nama yang sama boleh dipakai di kategori lain.
    $other = app(SaveIssueType::class)($admin, $printer, null, 'Putus-putus');

    expect($other->category_id)->toBe($printer->id);
});

test('an issue type can be renamed and toggled', function () {
    $admin = categoryAdmin();
    $category = Category::factory()->create();
    $type = IssueType::factory()->create(['category_id' => $category->id, 'name' => 'Lambat']);

    $renamed = app(SaveIssueType::class)($admin, $category, $type, 'Sangat lambat');

    expect($renamed->refresh()->name)->toBe('Sangat lambat');

    app(SetIssueTypeActive::class)($admin, $type, false);

    expect($type->refresh()->is_active)->toBeFalse();
});

test('an issue type cannot be edited through another category', function () {
    $category = Category::factory()->create();
    $otherType = IssueType::factory()->create();

    expect(fn () => app(SaveIssueType::class)(categoryAdmin(), $category, $otherType, 'Nama baru'))
        ->toThrow(InvalidArgumentException::class);
});

test('a regular user cannot manage issue types', function () {
    $category = Category::factory()->create();

    expect(fn () => app(SaveIssueType::class)(User::factory()->create(), $category, null, 'Lambat'))
        ->toThrow(AuthorizationException::class);
});
