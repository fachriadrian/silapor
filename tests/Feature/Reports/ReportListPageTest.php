<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

test('the list can be searched by code with or without the hash sign', function () {
    Report::factory()->create(['code' => 'WF001', 'title' => 'WiFi lemot']);
    Report::factory()->create(['code' => 'PRN001', 'title' => 'Printer macet']);

    Livewire::test('pages::reports.index')
        ->set('search', '#wf001')
        ->assertSee('WiFi lemot')
        ->assertDontSee('Printer macet');

    Livewire::test('pages::reports.index')
        ->set('search', 'PRN001')
        ->assertSee('Printer macet')
        ->assertDontSee('WiFi lemot');
});

test('the list can be filtered by status', function () {
    Report::factory()->create(['title' => 'Laporan baru']);
    Report::factory()->withStatus(ReportStatus::Completed)->create(['title' => 'Laporan selesai']);

    Livewire::test('pages::reports.index')
        ->set('status', ReportStatus::Completed->value)
        ->assertSee('Laporan selesai')
        ->assertDontSee('Laporan baru');
});

test('reporter names are masked for guests but visible to admins', function () {
    Report::factory()->create(['guest_name' => 'Budi Santoso']);

    Livewire::test('pages::reports.index')
        ->assertSee('Budi S.')
        ->assertDontSee('Budi Santoso');

    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();
    $this->actingAs($admin);

    Livewire::test('pages::reports.index')
        ->assertSee('Budi Santoso');
});

test('the list is paginated', function () {
    Report::factory()->count(12)->create();

    Livewire::test('pages::reports.index')
        ->assertSee('Halaman 1 dari 2');
});
