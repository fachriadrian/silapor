<?php

use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function panelAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('only admins see the management panel and the contact details', function () {
    $report = Report::factory()->create([
        'guest_name' => 'Budi Santoso',
        'guest_contact' => '081234567890',
    ]);

    $this->get(route('reports.show', $report->code))
        ->assertDontSee('Kelola Laporan (Staf IT)')
        ->assertDontSee('081234567890');

    $this->actingAs(User::factory()->create())
        ->get(route('reports.show', $report->code))
        ->assertDontSee('Kelola Laporan (Staf IT)')
        ->assertDontSee('081234567890');

    $this->actingAs(panelAdmin())
        ->get(route('reports.show', $report->code))
        ->assertSee('Kelola Laporan (Staf IT)')
        ->assertSee('081234567890');
});

test('an admin can change the status from the detail page', function () {
    $report = Report::factory()->create();

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('newStatus', 'diproses')
        ->set('statusNote', 'Teknisi menuju lokasi.')
        ->call('changeStatus')
        ->assertHasNoErrors()
        ->assertSee('Status laporan diubah');

    expect($report->refresh()->status)->toBe(ReportStatus::InProgress);
});

test('putting a report on hold requires a note', function () {
    $report = Report::factory()->withStatus(ReportStatus::InProgress)->create();

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('newStatus', 'ditunda')
        ->call('changeStatus')
        ->assertHasErrors('statusNote');

    expect($report->refresh()->status)->toBe(ReportStatus::InProgress);
});

test('an unknown status value is rejected', function () {
    $report = Report::factory()->create();

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('newStatus', 'ngawur')
        ->call('changeStatus')
        ->assertHasErrors('newStatus');
});

test('an admin can add a progress note from the detail page', function () {
    $report = Report::factory()->withStatus(ReportStatus::InProgress)->create();

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('progressNote', 'Menunggu teknisi vendor.')
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSee('Menunggu teknisi vendor.');

    expect($report->logs()->firstOrFail()->event_type)->toBe(LogEvent::Note)
        ->and($report->refresh()->status)->toBe(ReportStatus::InProgress);
});

test('a blank progress note is rejected', function () {
    $report = Report::factory()->create();

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->call('addNote')
        ->assertHasErrors(['progressNote' => 'required']);
});

test('an admin can change the urgency from the detail page', function () {
    $report = Report::factory()->create(['urgency' => ReportUrgency::Medium]);

    $this->actingAs(panelAdmin());

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('urgencyValue', 'tinggi')
        ->call('changeUrgency')
        ->assertHasNoErrors();

    expect($report->refresh()->urgency)->toBe(ReportUrgency::High);
});

test('regular users cannot use the management actions', function () {
    $user = User::factory()->create();
    $report = Report::factory()->forUser($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('newStatus', 'diproses')
        ->call('changeStatus')
        ->assertForbidden();

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('progressNote', 'Iseng.')
        ->call('addNote')
        ->assertForbidden();

    Livewire::test('pages::reports.show', ['report' => $report])
        ->set('urgencyValue', 'tinggi')
        ->call('changeUrgency')
        ->assertForbidden();

    expect($report->refresh()->status)->toBe(ReportStatus::New);
});
