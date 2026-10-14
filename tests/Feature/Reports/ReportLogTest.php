<?php

use App\Enums\LogEvent;
use App\Models\Report;
use App\Models\ReportLog;

test('report logs cannot be updated', function () {
    $report = Report::factory()->create();
    $log = ReportLog::record($report, LogEvent::Note, null, 'Catatan awal', guestName: 'Tamu');

    $log->note = 'Diubah';

    expect(fn () => $log->save())->toThrow(LogicException::class);
    expect($log->refresh()->note)->toBe('Catatan awal');
});

test('report logs cannot be deleted', function () {
    $report = Report::factory()->create();
    $log = ReportLog::record($report, LogEvent::Note, null, 'Catatan awal', guestName: 'Tamu');

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(ReportLog::count())->toBe(1);
});
