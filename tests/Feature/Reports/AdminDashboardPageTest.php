<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function dashAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('the dashboard counts active reports per status and ignores finished ones', function () {
    Report::factory()->count(2)->create();
    Report::factory()->withStatus(ReportStatus::InProgress)->create();
    Report::factory()->withStatus(ReportStatus::OnHold)->count(3)->create();
    Report::factory()->withStatus(ReportStatus::Completed)->count(4)->create();

    $this->actingAs(dashAdmin());

    $component = Livewire::test('pages::admin.dashboard');

    expect($component->instance()->counts)->toBe(['baru' => 2, 'diproses' => 1, 'ditunda' => 3]);
});

test('the newest unhandled reports are highlighted, newest first', function () {
    Report::factory()->create(['title' => 'Laporan lama', 'created_at' => now()->subDays(2)]);
    Report::factory()->create(['title' => 'Laporan terbaru', 'created_at' => now()->subHour()]);
    Report::factory()->withStatus(ReportStatus::InProgress)->create(['title' => 'Laporan dikerjakan']);

    $this->actingAs(dashAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertSeeInOrder(['Laporan terbaru', 'Laporan lama'])
        ->assertDontSee('Laporan dikerjakan');
});

test('reports without recent updates are listed under needs attention', function () {
    Report::factory()->withStatus(ReportStatus::InProgress)->create([
        'title' => 'Laporan tertinggal',
        'updated_at' => now()->subDays(5),
    ]);
    Report::factory()->withStatus(ReportStatus::InProgress)->create(['title' => 'Laporan segar']);
    Report::factory()->withStatus(ReportStatus::Completed)->create([
        'title' => 'Laporan selesai lama',
        'updated_at' => now()->subDays(30),
    ]);

    $this->actingAs(dashAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Laporan tertinggal')
        ->assertDontSee('Laporan segar')
        ->assertDontSee('Laporan selesai lama');
});
