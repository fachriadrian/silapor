<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Support\ReportStatistics;
use App\Support\StatisticsPeriod;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Waktu dibekukan agar batas periode dan bulan konsisten.
    $this->travelTo(Carbon::parse('2026-10-15 10:00:00', 'UTC'));
});

function statsFor(string $period = '30', bool $includeCancelled = false): ReportStatistics
{
    return new ReportStatistics(StatisticsPeriod::make($period), $includeCancelled);
}

function reportDaysAgo(int $days, array $attributes = []): Report
{
    return Report::factory()->create(array_merge(['created_at' => now()->subDays($days)], $attributes));
}

test('the summary counts reports in the period and excludes cancelled ones by default', function () {
    reportDaysAgo(1);
    reportDaysAgo(2, ['status' => ReportStatus::InProgress]);
    reportDaysAgo(3, ['status' => ReportStatus::Completed, 'completed_at' => now()->subDay()]);
    reportDaysAgo(4, ['status' => ReportStatus::Cancelled]);
    reportDaysAgo(60);

    $summary = statsFor()->summary();

    expect($summary['total'])->toBe(3)
        ->and($summary['active'])->toBe(2)
        ->and($summary['completed'])->toBe(1)
        ->and($summary['cancelled'])->toBe(1);

    expect(statsFor('30', true)->summary()['total'])->toBe(4);
});

test('the period decides which reports are counted', function () {
    reportDaysAgo(2);
    reportDaysAgo(20);
    reportDaysAgo(60);

    expect(statsFor('7')->summary()['total'])->toBe(1)
        ->and(statsFor('30')->summary()['total'])->toBe(2)
        ->and(statsFor('90')->summary()['total'])->toBe(3);
});

test('a custom period only includes the chosen days', function () {
    reportDaysAgo(0);
    reportDaysAgo(20);

    $period = StatisticsPeriod::make('custom', '2026-10-15', '2026-10-15');

    expect((new ReportStatistics($period))->summary()['total'])->toBe(1);
});

test('the average resolution time is measured from creation to completion', function () {
    reportDaysAgo(5, ['status' => ReportStatus::Completed, 'completed_at' => now()->subDays(5)->addHours(10)]);
    reportDaysAgo(4, ['status' => ReportStatus::Completed, 'completed_at' => now()->subDays(4)->addHours(20)]);
    reportDaysAgo(3);

    expect(statsFor()->summary()['average_resolution_hours'])->toEqualWithDelta(15.0, 0.01);
});

test('the average resolution time is null when nothing has been completed', function () {
    reportDaysAgo(3);

    expect(statsFor()->summary()['average_resolution_hours'])->toBeNull();
});

test('reports are grouped per category with counts and percentages', function () {
    $wifi = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);
    $printer = Category::factory()->create(['name' => 'Printer', 'prefix' => 'PRN']);

    foreach ([1, 2, 3] as $days) {
        reportDaysAgo($days, ['category_id' => $wifi->id]);
    }
    reportDaysAgo(4, ['category_id' => $printer->id]);

    $rows = statsFor()->byCategory();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('Jaringan WiFi')
        ->and($rows[0]['count'])->toBe(3)
        ->and($rows[0]['percent'])->toBe(75)
        ->and($rows[1]['name'])->toBe('Printer')
        ->and($rows[1]['percent'])->toBe(25);
});

test('the resolution time is averaged per category, slowest first', function () {
    $wifi = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);
    $printer = Category::factory()->create(['name' => 'Printer', 'prefix' => 'PRN']);

    reportDaysAgo(5, [
        'category_id' => $wifi->id,
        'status' => ReportStatus::Completed,
        'completed_at' => now()->subDays(5)->addHours(10),
    ]);
    reportDaysAgo(5, [
        'category_id' => $printer->id,
        'status' => ReportStatus::Completed,
        'completed_at' => now()->subDays(5)->addHours(30),
    ]);

    $rows = statsFor()->resolutionByCategory();

    expect($rows[0]['name'])->toBe('Printer')
        ->and($rows[0]['average_hours'])->toEqualWithDelta(30.0, 0.01)
        ->and($rows[1]['name'])->toBe('Jaringan WiFi');
});

test('recurring issues are grouped by category, issue type and a normalised location', function () {
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);

    // Tiga laporan dalam 30 hari, dengan penulisan lokasi yang berbeda-beda.
    foreach ([[1, 'Lantai 2'], [3, 'lantai  2'], [5, ' LANTAI 2 ']] as [$days, $location]) {
        reportDaysAgo($days, ['category_id' => $category->id, 'location' => $location]);
    }

    // Dua laporan: terdaftar, tetapi belum mencapai ambang.
    foreach ([2, 4] as $days) {
        reportDaysAgo($days, ['category_id' => $category->id, 'location' => 'Ruang Server']);
    }

    // Satu laporan saja: tidak ditampilkan.
    reportDaysAgo(6, ['category_id' => $category->id, 'location' => 'Lobi']);

    $rows = collect(statsFor()->recurring(3, 30));

    expect($rows)->toHaveCount(2);

    $floor = $rows->first(fn (array $row) => strtolower($row['location']) === 'lantai 2');
    $server = $rows->first(fn (array $row) => $row['location'] === 'Ruang Server');

    expect($floor['count'])->toBe(3)
        ->and($floor['recurring'])->toBeTrue()
        ->and($server['count'])->toBe(2)
        ->and($server['recurring'])->toBeFalse();
});

test('reports that are far apart in time are not flagged as recurring', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    foreach ([5, 45, 85] as $days) {
        reportDaysAgo($days, ['category_id' => $category->id, 'location' => 'Gudang']);
    }

    $rows = statsFor('90')->recurring(3, 30);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['count'])->toBe(3)
        ->and($rows[0]['recurring'])->toBeFalse();
});

test('different issue types at the same place are not mixed together', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $slow = IssueType::factory()->create(['category_id' => $category->id, 'name' => 'Lambat']);

    reportDaysAgo(1, ['category_id' => $category->id, 'location' => 'Lantai 2', 'issue_type_id' => $slow->id]);
    reportDaysAgo(2, ['category_id' => $category->id, 'location' => 'Lantai 2']);

    expect(statsFor()->recurring(3, 30))->toBeEmpty();
});

test('the monthly trend counts reports per category for the last six months', function () {
    $category = Category::factory()->create(['name' => 'Jaringan WiFi', 'prefix' => 'WF']);

    reportDaysAgo(1, ['category_id' => $category->id]);
    reportDaysAgo(2, ['category_id' => $category->id]);
    reportDaysAgo(20, ['category_id' => $category->id]);
    reportDaysAgo(300, ['category_id' => $category->id]);

    $trend = statsFor()->monthlyTrend();

    expect($trend['months'])->toHaveCount(6)
        ->and($trend['rows'])->toHaveCount(1)
        ->and($trend['rows'][0]['counts'])->toBe([0, 0, 0, 0, 1, 2])
        ->and($trend['rows'][0]['total'])->toBe(3)
        ->and($trend['max'])->toBe(2);
});

test('the monthly trend leaves out cancelled reports unless asked', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    reportDaysAgo(1, ['category_id' => $category->id, 'status' => ReportStatus::Cancelled]);

    expect(statsFor()->monthlyTrend()['rows'])->toBeEmpty();
    expect(statsFor('30', true)->monthlyTrend()['rows'])->toHaveCount(1);
});
