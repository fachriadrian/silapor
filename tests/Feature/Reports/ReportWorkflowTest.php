<?php

use App\Actions\Reports\AddReportNote;
use App\Actions\Reports\CancelReport;
use App\Actions\Reports\ChangeReportStatus;
use App\Enums\ActorType;
use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function workflowAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('an admin can change the status and the change is logged with from and to status', function () {
    $admin = workflowAdmin();
    $report = Report::factory()->create();

    $updated = app(ChangeReportStatus::class)($report, ReportStatus::InProgress, $admin, 'Teknisi menuju lokasi.');

    expect($updated->status)->toBe(ReportStatus::InProgress);

    $log = $updated->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::StatusChanged)
        ->and($log->from_status)->toBe(ReportStatus::New)
        ->and($log->to_status)->toBe(ReportStatus::InProgress)
        ->and($log->note)->toBe('Teknisi menuju lokasi.')
        ->and($log->actor_type)->toBe(ActorType::Admin)
        ->and($log->actor_name)->toBe($admin->name);
});

test('completing sets completed_at, and reopening clears it and requires a note', function () {
    $admin = workflowAdmin();
    $report = Report::factory()->withStatus(ReportStatus::InProgress)->create();
    $change = app(ChangeReportStatus::class);

    $completed = $change($report, ReportStatus::Completed, $admin);

    expect($completed->completed_at)->not->toBeNull();

    expect(fn () => $change($completed, ReportStatus::InProgress, $admin))
        ->toThrow(ValidationException::class);

    $reopened = $change($completed, ReportStatus::InProgress, $admin, 'Masalah muncul lagi.');

    expect($reopened->completed_at)->toBeNull()
        ->and($reopened->status)->toBe(ReportStatus::InProgress)
        ->and($reopened->logs()->get()->last()->event_type)->toBe(LogEvent::Reopened);
});

test('invalid transitions are rejected and nothing changes', function () {
    $admin = workflowAdmin();
    $report = Report::factory()->create();

    expect(fn () => app(ChangeReportStatus::class)($report, ReportStatus::OnHold, $admin, 'Menunggu.'))
        ->toThrow(ValidationException::class);

    expect($report->refresh()->status)->toBe(ReportStatus::New)
        ->and($report->logs()->count())->toBe(0);
});

test('putting a report on hold or cancelling it requires a note', function () {
    $admin = workflowAdmin();
    $change = app(ChangeReportStatus::class);
    $report = Report::factory()->withStatus(ReportStatus::InProgress)->create();

    expect(fn () => $change($report, ReportStatus::OnHold, $admin))
        ->toThrow(ValidationException::class);

    expect(fn () => $change($report, ReportStatus::Cancelled, $admin, '   '))
        ->toThrow(ValidationException::class);

    expect($report->refresh()->status)->toBe(ReportStatus::InProgress);
});

test('a regular user cannot change the status of a report', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    expect(fn () => app(ChangeReportStatus::class)($report, ReportStatus::InProgress, $user))
        ->toThrow(AuthorizationException::class);
});

test('a user can cancel their own report with a reason and it stays in the log', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    $cancelled = app(CancelReport::class)($report, $user, 'Masalah sudah selesai sendiri.');

    expect($cancelled->status)->toBe(ReportStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull();

    $log = $cancelled->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::Cancelled)
        ->and($log->actor_type)->toBe(ActorType::User)
        ->and($log->note)->toBe('Masalah sudah selesai sendiri.')
        ->and($log->from_status)->toBe(ReportStatus::New)
        ->and($log->to_status)->toBe(ReportStatus::Cancelled);
});

test('cancelling requires a reason', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    expect(fn () => app(CancelReport::class)($report, $user, '  '))
        ->toThrow(ValidationException::class);

    expect($report->refresh()->status)->toBe(ReportStatus::New);
});

test('a user cannot cancel someone elses report', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $report = Report::factory()->forUser($owner)->create();

    expect(fn () => app(CancelReport::class)($report, $other, 'Iseng.'))
        ->toThrow(AuthorizationException::class);

    expect($report->refresh()->status)->toBe(ReportStatus::New);
});

test('a finished report cannot be cancelled', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->withStatus(ReportStatus::Completed)->create();

    expect(fn () => app(CancelReport::class)($report, $user, 'Terlambat.'))
        ->toThrow(AuthorizationException::class);
});

test('an admin can cancel any active report with a reason', function () {
    $admin = workflowAdmin();
    $report = Report::factory()->create();

    $cancelled = app(CancelReport::class)($report, $admin, 'Laporan duplikat.');

    $log = $cancelled->logs()->firstOrFail();

    expect($cancelled->status)->toBe(ReportStatus::Cancelled)
        ->and($log->actor_type)->toBe(ActorType::Admin);
});

test('an admin can add a progress note without changing the status', function () {
    $admin = workflowAdmin();
    $report = Report::factory()->withStatus(ReportStatus::InProgress)
        ->create(['updated_at' => now()->subDay()]);

    app(AddReportNote::class)($report, $admin, 'Menunggu teknisi vendor, estimasi Kamis.');

    $log = $report->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::Note)
        ->and($log->note)->toBe('Menunggu teknisi vendor, estimasi Kamis.')
        ->and($log->to_status)->toBeNull()
        ->and($report->refresh()->status)->toBe(ReportStatus::InProgress)
        ->and($report->updated_at->isAfter(now()->subHour()))->toBeTrue();
});

test('only an admin can add notes and blank notes are rejected', function () {
    $admin = workflowAdmin();
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    expect(fn () => app(AddReportNote::class)($report, $user, 'Catatan.'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(AddReportNote::class)($report, $admin, '   '))
        ->toThrow(ValidationException::class);

    expect($report->logs()->count())->toBe(0);
});
