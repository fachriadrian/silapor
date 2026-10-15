<?php

use App\Actions\Reports\ChangeReportUrgency;
use App\Enums\LogEvent;
use App\Enums\ReportUrgency;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

function urgencyAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('an admin can change the urgency and the change is logged', function () {
    $report = Report::factory()->create(['urgency' => ReportUrgency::Medium]);

    $updated = app(ChangeReportUrgency::class)($report, ReportUrgency::High, urgencyAdmin());

    expect($updated->urgency)->toBe(ReportUrgency::High);

    $log = $updated->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::UrgencyChanged)
        ->and($log->note)->toContain('Sedang')
        ->and($log->note)->toContain('Tinggi');
});

test('setting the same urgency changes nothing and adds no log', function () {
    $report = Report::factory()->create(['urgency' => ReportUrgency::Medium]);

    app(ChangeReportUrgency::class)($report, ReportUrgency::Medium, urgencyAdmin());

    expect($report->logs()->count())->toBe(0);
});

test('a regular user cannot change the urgency', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    expect(fn () => app(ChangeReportUrgency::class)($report, ReportUrgency::High, $user))
        ->toThrow(AuthorizationException::class);
});
