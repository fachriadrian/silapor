<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('reports.mine'))->assertRedirect(route('login'));
});

test('users only see their own reports', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Report::factory()->forUser($me)->create(['title' => 'Laporan milik saya']);
    Report::factory()->forUser($other)->create(['title' => 'Laporan orang lain']);

    $this->actingAs($me);

    Livewire::test('pages::reports.mine')
        ->assertSee('Laporan milik saya')
        ->assertDontSee('Laporan orang lain');
});

test('tabs separate active reports from finished ones', function () {
    $me = User::factory()->create();

    Report::factory()->forUser($me)->create(['title' => 'Laporan aktif saya']);
    Report::factory()->forUser($me)->withStatus(ReportStatus::Completed)->create(['title' => 'Laporan selesai saya']);
    Report::factory()->forUser($me)->withStatus(ReportStatus::Cancelled)->create(['title' => 'Laporan batal saya']);

    $this->actingAs($me);

    Livewire::test('pages::reports.mine')
        ->assertSee('Laporan aktif saya')
        ->assertDontSee('Laporan selesai saya')
        ->call('setTab', 'riwayat')
        ->assertSee('Laporan selesai saya')
        ->assertSee('Laporan batal saya')
        ->assertDontSee('Laporan aktif saya')
        ->call('setTab', 'semua')
        ->assertSee('Laporan aktif saya')
        ->assertSee('Laporan selesai saya')
        ->assertSee('Laporan batal saya');
});

test('an unknown tab falls back to the active tab', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::reports.mine')
        ->call('setTab', 'ngawur')
        ->assertSet('tab', 'aktif');
});
