<?php

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function listAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('guests are redirected to login and regular users are forbidden', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});

test('finished reports are hidden by default and can be shown with the toggle', function () {
    Report::factory()->create(['title' => 'Laporan aktif']);
    Report::factory()->withStatus(ReportStatus::Completed)->create(['title' => 'Laporan selesai']);
    Report::factory()->withStatus(ReportStatus::Cancelled)->create(['title' => 'Laporan batal']);

    $this->actingAs(listAdmin());

    Livewire::test('pages::admin.reports.index')
        ->assertSee('Laporan aktif')
        ->assertDontSee('Laporan selesai')
        ->assertDontSee('Laporan batal')
        ->set('showArchived', true)
        ->assertSee('Laporan aktif')
        ->assertSee('Laporan selesai')
        ->assertSee('Laporan batal');
});

test('choosing a status explicitly shows it even without the toggle', function () {
    Report::factory()->create(['title' => 'Laporan aktif']);
    Report::factory()->withStatus(ReportStatus::Completed)->create(['title' => 'Laporan selesai']);

    $this->actingAs(listAdmin());

    Livewire::test('pages::admin.reports.index')
        ->set('status', 'selesai')
        ->assertSee('Laporan selesai')
        ->assertDontSee('Laporan aktif');
});

test('the list can be filtered by urgency and searched', function () {
    Report::factory()->create(['title' => 'Printer macet', 'urgency' => ReportUrgency::High]);
    Report::factory()->create(['title' => 'WiFi lemot', 'urgency' => ReportUrgency::Low]);

    $this->actingAs(listAdmin());

    Livewire::test('pages::admin.reports.index')
        ->set('urgency', 'tinggi')
        ->assertSee('Printer macet')
        ->assertDontSee('WiFi lemot')
        ->set('urgency', '')
        ->set('search', 'wifi')
        ->assertSee('WiFi lemot')
        ->assertDontSee('Printer macet');
});

test('the list can be sorted by highest urgency first', function () {
    Report::factory()->create(['title' => 'Laporan rendah', 'urgency' => ReportUrgency::Low]);
    Report::factory()->create(['title' => 'Laporan sedang', 'urgency' => ReportUrgency::Medium]);
    Report::factory()->create(['title' => 'Laporan tinggi', 'urgency' => ReportUrgency::High]);

    $this->actingAs(listAdmin());

    Livewire::test('pages::admin.reports.index')
        ->set('sort', 'urgensi')
        ->assertSeeInOrder(['Laporan tinggi', 'Laporan sedang', 'Laporan rendah']);
});
