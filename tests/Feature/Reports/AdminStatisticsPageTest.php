<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function statsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('guests are redirected to login and regular users are forbidden', function () {
    $this->get(route('admin.statistics'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.statistics'))
        ->assertForbidden();
});

test('admins can open the statistics page', function () {
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);
    Report::factory()->count(2)->create(['category_id' => $category->id]);

    $this->actingAs(statsAdmin())
        ->get(route('admin.statistics'))
        ->assertOk()
        ->assertSee('Statistik Laporan')
        ->assertSee('Jaringan WiFi');
});

test('the period can be narrowed to a custom range', function () {
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'UTC'));

    $today = Category::factory()->create(['name' => 'Kategori Hari Ini', 'prefix' => 'AAA']);
    $older = Category::factory()->create(['name' => 'Kategori Lama', 'prefix' => 'BBB']);

    Report::factory()->create(['category_id' => $today->id]);
    Report::factory()->create(['category_id' => $older->id, 'created_at' => now()->subDays(20)]);

    $this->actingAs(statsAdmin());

    Livewire::test('pages::admin.statistics')
        ->assertSee('Kategori Hari Ini')
        ->assertSee('Kategori Lama')
        ->set('period', 'custom')
        ->set('from', '2026-10-15')
        ->set('to', '2026-10-15')
        ->assertSee('Kategori Hari Ini')
        ->assertDontSee('Kategori Lama');
});

test('cancelled reports are only analysed when asked', function () {
    $category = Category::factory()->create(['name' => 'Kategori Batal', 'prefix' => 'CCC']);
    Report::factory()->withStatus(ReportStatus::Cancelled)->create(['category_id' => $category->id]);

    $this->actingAs(statsAdmin());

    Livewire::test('pages::admin.statistics')
        ->assertDontSee('Kategori Batal')
        ->set('includeCancelled', true)
        ->assertSee('Kategori Batal');
});

test('the recurring threshold is kept within sensible bounds', function () {
    $this->actingAs(statsAdmin());

    $component = Livewire::test('pages::admin.statistics')
        ->set('threshold', 99);

    expect($component->instance()->effectiveThreshold)->toBe(20);

    $component->set('threshold', 1);

    expect($component->instance()->effectiveThreshold)->toBe(2);
});
