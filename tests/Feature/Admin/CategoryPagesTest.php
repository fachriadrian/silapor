<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function categoryPagesAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('only admins can open the category pages', function () {
    $category = Category::factory()->create();

    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.show', $category))
        ->assertForbidden();

    $this->actingAs(categoryPagesAdmin())
        ->get(route('admin.categories.index'))
        ->assertOk();
});

test('the page actions refuse regular users even when called directly', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.categories.index')
        ->set('name', 'Perangkat Audio')
        ->set('prefix', 'PRA')
        ->call('add')
        ->assertForbidden();

    expect(Category::count())->toBe(0);
});

test('an admin can add a category from the page', function () {
    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.index')
        ->set('name', 'Perangkat Audio')
        ->set('prefix', 'pra')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSee('Perangkat Audio');

    expect(Category::where('prefix', 'PRA')->exists())->toBeTrue();
});

test('invalid input shows errors on the form fields', function () {
    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.index')
        ->set('name', '')
        ->set('prefix', 'A1')
        ->call('add')
        ->assertHasErrors(['name', 'prefix']);

    expect(Category::count())->toBe(0);
});

test('an admin can edit a category', function () {
    $category = Category::factory()->create(['name' => 'Nama Lama', 'prefix' => 'LMA']);

    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.index')
        ->call('edit', $category->id)
        ->assertSet('editName', 'Nama Lama')
        ->assertSet('editPrefix', 'LMA')
        ->set('editName', 'Nama Baru')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($category->refresh()->name)->toBe('Nama Baru');
});

test('a locked prefix is reported on the edit field', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    Report::factory()->for($category)->create();

    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.index')
        ->call('edit', $category->id)
        ->set('editPrefix', 'WIFI')
        ->call('saveEdit')
        ->assertHasErrors('editPrefix');

    expect($category->refresh()->prefix)->toBe('WF');
});

test('a category can be toggled but the last active one is protected', function () {
    $first = Category::factory()->create(['name' => 'Kategori Pertama']);
    $second = Category::factory()->create(['name' => 'Kategori Kedua']);

    $this->actingAs(categoryPagesAdmin());

    $component = Livewire::test('pages::admin.categories.index')
        ->call('toggleActive', $first->id)
        ->assertHasNoErrors()
        ->assertSee('Nonaktif');

    expect($first->refresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $second->id)
        ->assertHasErrors('toggle');

    expect($second->refresh()->is_active)->toBeTrue();
});

test('an admin can manage the issue types of a category', function () {
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);

    $this->actingAs(categoryPagesAdmin());

    $component = Livewire::test('pages::admin.categories.show', ['category' => $category])
        ->set('newName', 'Putus-putus')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('Putus-putus');

    $type = IssueType::where('category_id', $category->id)->firstOrFail();

    $component->call('edit', $type->id)
        ->set('editName', 'Sering putus')
        ->call('saveEdit')
        ->assertHasNoErrors()
        ->assertSee('Sering putus')
        ->call('toggleActive', $type->id)
        ->assertSee('Nonaktif');

    expect($type->refresh()->is_active)->toBeFalse();
});

test('a duplicate issue type name is reported on the form', function () {
    $category = Category::factory()->create();
    IssueType::factory()->create(['category_id' => $category->id, 'name' => 'Lambat']);

    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.show', ['category' => $category])
        ->set('newName', 'Lambat')
        ->call('add')
        ->assertHasErrors('newName');
});

test('an issue type of another category cannot be edited from this page', function () {
    $category = Category::factory()->create();
    $otherType = IssueType::factory()->create();

    $this->actingAs(categoryPagesAdmin());

    Livewire::test('pages::admin.categories.show', ['category' => $category])
        ->call('edit', $otherType->id)
        ->assertNotFound();
});
