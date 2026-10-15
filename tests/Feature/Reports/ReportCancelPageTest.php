<?php

use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

test('the owner sees the cancel button on an active report', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->create();

    $this->actingAs($owner)
        ->get(route('reports.show', $report->code))
        ->assertSee('Batalkan Laporan');
});

test('guests and other users do not see the cancel button', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->create();

    $this->get(route('reports.show', $report->code))
        ->assertDontSee('Batalkan Laporan');

    $this->actingAs(User::factory()->create())
        ->get(route('reports.show', $report->code))
        ->assertDontSee('Batalkan Laporan');
});

test('the cancel button is hidden once the report is finished', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->withStatus(ReportStatus::Completed)->create();

    $this->actingAs($owner)
        ->get(route('reports.show', $report->code))
        ->assertDontSee('Batalkan Laporan');
});

test('the owner can cancel with a reason and the log keeps the record', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->create();

    $this->actingAs($owner);

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('cancelReason', 'Masalah sudah selesai sendiri.')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertSee('Laporan telah dibatalkan');

    expect($report->refresh()->status)->toBe(ReportStatus::Cancelled);

    $log = $report->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::Cancelled)
        ->and($log->note)->toBe('Masalah sudah selesai sendiri.');
});

test('cancelling requires a reason', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->create();

    $this->actingAs($owner);

    Livewire::test('pages::reports.show', ['report' => $report])
        ->call('cancel')
        ->assertHasErrors(['cancelReason' => 'required']);

    expect($report->refresh()->status)->toBe(ReportStatus::New);
});

test('another user cannot cancel the report', function () {
    $report = Report::factory()->forUser(User::factory()->create())->create();

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('cancelReason', 'Iseng.')
        ->call('cancel')
        ->assertForbidden();

    expect($report->refresh()->status)->toBe(ReportStatus::New);
});

test('a finished report cannot be cancelled', function () {
    $owner = User::factory()->create();
    $report = Report::factory()->forUser($owner)->withStatus(ReportStatus::Completed)->create();

    $this->actingAs($owner);

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('cancelReason', 'Terlambat.')
        ->call('cancel')
        ->assertForbidden();
});

test('an admin can cancel any active report from the detail page', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();
    $report = Report::factory()->create();

    $this->actingAs($admin);

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('cancelReason', 'Laporan duplikat.')
        ->call('cancel')
        ->assertHasNoErrors();

    expect($report->refresh()->status)->toBe(ReportStatus::Cancelled);
});
