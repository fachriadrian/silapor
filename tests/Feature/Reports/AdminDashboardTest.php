<?php

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function dashboardAdmin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => UserRole::Admin])->save();

    return $admin;
}

test('the dashboard shows the number of active reports per status', function () {
    Report::factory()->count(3)->create();
    Report::factory()->create(['urgency' => ReportUrgency::High]);
    Report::factory()->count(2)->withStatus(ReportStatus::InProgress)->create();
    Report::factory()->withStatus(ReportStatus::OnHold)->create();
    Report::factory()->count(4)->withStatus(ReportStatus::Completed)->create();

    $this->actingAs(dashboardAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertSeeHtml('data-test="count-baru">4</p>')
        ->assertSeeHtml('data-test="count-diproses">2</p>')
        ->assertSeeHtml('data-test="count-ditunda">1</p>')
        ->assertSeeHtml('data-test="count-urgen">1</p>');
});

test('the highlight lists only new reports, newest first, up to ten', function () {
    foreach (range(1, 11) as $i) {
        Report::factory()->create([
            'title' => 'Judul-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'created_at' => now()->subMinutes(60 - $i),
        ]);
    }

    Report::factory()->withStatus(ReportStatus::Completed)->create(['title' => 'Judul-selesai']);

    $this->actingAs(dashboardAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertSeeInOrder(['Judul-11', 'Judul-10', 'Judul-02'])
        ->assertDontSee('Judul-01')
        ->assertDontSee('Judul-selesai')
        ->assertSee('Lihat semua (11)');
});

test('active reports that were not updated for days are flagged for attention', function () {
    Report::factory()->withStatus(ReportStatus::InProgress)->create([
        'title' => 'Macet-lama',
        'updated_at' => now()->subDays(5),
    ]);
    Report::factory()->withStatus(ReportStatus::InProgress)->create([
        'title' => 'Macet-baru',
        'updated_at' => now()->subHour(),
    ]);
    Report::factory()->withStatus(ReportStatus::Completed)->create([
        'title' => 'Selesai-lama',
        'updated_at' => now()->subDays(30),
    ]);

    $this->actingAs(dashboardAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertSee('Perlu perhatian')
        ->assertSee('Macet-lama')
        ->assertDontSee('Macet-baru')
        ->assertDontSee('Selesai-lama');
});

test('the attention section is hidden when nothing is stale', function () {
    Report::factory()->withStatus(ReportStatus::InProgress)->create(['updated_at' => now()->subHour()]);

    $this->actingAs(dashboardAdmin());

    Livewire::test('pages::admin.dashboard')
        ->assertDontSee('Perlu perhatian');
});
