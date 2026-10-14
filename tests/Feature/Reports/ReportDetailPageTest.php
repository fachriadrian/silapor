<?php

use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Models\Category;
use App\Models\User;

test('the detail page shows the log, with the guest name masked and no contact details', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    $report = app(CreateReport::class)(new NewReportData(
        categoryId: $category->id,
        description: 'WiFi putus-putus di ruang rapat.',
        location: 'Ruang Rapat Lt. 2',
        guestName: 'Budi Santoso',
        guestContact: '081234567890',
    ));

    $this->get(route('reports.show', $report->code))
        ->assertOk()
        ->assertSee('#WF001')
        ->assertSee('Laporan dibuat')
        ->assertSee('Budi S.')
        ->assertDontSee('Budi Santoso')
        ->assertDontSee('081234567890');
});

test('the owner sees their full name while other visitors see it masked', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $owner = User::factory()->create(['name' => 'Siti Aminah']);

    $report = app(CreateReport::class)(
        new NewReportData(categoryId: $category->id, description: 'WiFi putus-putus.', location: 'Lt. 1'),
        $owner,
    );

    $this->get(route('reports.show', $report->code))
        ->assertSee('Siti A.')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($owner)
        ->get(route('reports.show', $report->code))
        ->assertSee('Siti Aminah');
});

test('the confirmation banner appears only right after the report is created', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    $report = app(CreateReport::class)(
        new NewReportData(
            categoryId: $category->id,
            description: 'WiFi putus-putus.',
            location: 'Lt. 1',
            guestName: 'Budi',
            guestContact: '0812',
        ),
    );

    $this->withSession(['report_created' => $report->code])
        ->get(route('reports.show', $report->code))
        ->assertSee('Laporan terkirim');

    $this->get(route('reports.show', $report->code))
        ->assertDontSee('Laporan terkirim');
});
