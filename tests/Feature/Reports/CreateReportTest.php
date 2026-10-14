<?php

use App\Actions\Reports\CancelReport;
use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Enums\ActorType;
use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('a registered user can create a report with a unique code and a creation log', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $user = User::factory()->create();

    $report = app(CreateReport::class)(
        new NewReportData(
            categoryId: $category->id,
            description: 'WiFi putus-putus di ruang rapat.',
            location: 'Ruang Rapat Lt. 2',
            urgency: ReportUrgency::High,
        ),
        $user,
    );

    expect($report->code)->toBe('WF001')
        ->and($report->status)->toBe(ReportStatus::New)
        ->and($report->urgency)->toBe(ReportUrgency::High)
        ->and($report->reporter_user_id)->toEqual($user->id)
        ->and($report->guest_name)->toBeNull()
        ->and($report->title)->toBe('Kendala '.$category->name);

    $log = $report->logs()->firstOrFail();

    expect($log->event_type)->toBe(LogEvent::Created)
        ->and($log->actor_type)->toBe(ActorType::User)
        ->and($log->actor_id)->toEqual($user->id)
        ->and($log->to_status)->toBe(ReportStatus::New);
});

test('a guest report stores the guest identity and logs a guest actor', function () {
    $category = Category::factory()->create(['prefix' => 'KOM']);

    $report = app(CreateReport::class)(new NewReportData(
        categoryId: $category->id,
        description: 'Komputer tidak mau menyala.',
        location: 'Ruang Keuangan',
        title: 'PC mati total',
        guestName: 'Budi Santoso',
        guestContact: '081234567890',
    ));

    expect($report->code)->toBe('KOM001')
        ->and($report->reporter_user_id)->toBeNull()
        ->and($report->guest_name)->toBe('Budi Santoso')
        ->and($report->guest_contact)->toBe('081234567890')
        ->and($report->title)->toBe('PC mati total');

    $log = $report->logs()->firstOrFail();

    expect($log->actor_type)->toBe(ActorType::Guest)
        ->and($log->actor_id)->toBeNull()
        ->and($log->actor_name)->toBe('Budi Santoso');
});

test('a guest report without a name or contact is rejected', function () {
    $category = Category::factory()->create();

    $create = fn (?string $name, ?string $contact) => app(CreateReport::class)(new NewReportData(
        categoryId: $category->id,
        description: 'Printer macet.',
        location: 'Lt. 1',
        guestName: $name,
        guestContact: $contact,
    ));

    expect(fn () => $create(null, '0812'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $create('Budi', '   '))->toThrow(InvalidArgumentException::class);
    expect(Report::count())->toBe(0);
});

test('an inactive category cannot receive reports', function () {
    $category = Category::factory()->create(['is_active' => false]);

    expect(fn () => app(CreateReport::class)(
        new NewReportData(categoryId: $category->id, description: 'Tes.', location: 'Lt. 1'),
        User::factory()->create(),
    ))->toThrow(ModelNotFoundException::class);

    expect(Report::count())->toBe(0);
});

test('an issue type from another category is rejected', function () {
    $category = Category::factory()->create();
    $otherType = IssueType::factory()->create();

    expect(fn () => app(CreateReport::class)(
        new NewReportData(
            categoryId: $category->id,
            description: 'Tes.',
            location: 'Lt. 1',
            issueTypeId: $otherType->id,
        ),
        User::factory()->create(),
    ))->toThrow(ModelNotFoundException::class);
});

test('many reports in the same category get distinct consecutive codes', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $user = User::factory()->create();
    $create = app(CreateReport::class);
    $data = new NewReportData(categoryId: $category->id, description: 'Lambat.', location: 'Lt. 1');

    $codes = [];

    foreach (range(1, 5) as $ignored) {
        $codes[] = $create($data, $user)->code;
    }

    expect($codes)->toBe(['WF001', 'WF002', 'WF003', 'WF004', 'WF005']);
});

test('report codes are never reused, even after a report is cancelled', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $user = User::factory()->create();
    $create = app(CreateReport::class);
    $data = new NewReportData(categoryId: $category->id, description: 'Lambat.', location: 'Lt. 1');

    $first = $create($data, $user);
    app(CancelReport::class)($first, $user, 'Sudah normal kembali.');
    $second = $create($data, $user);

    expect($first->code)->toBe('WF001')
        ->and($second->code)->toBe('WF002');
});
