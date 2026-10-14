<?php

use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Models\User;
use Livewire\Livewire;

function fillReportForm($component, Category $category)
{
    return $component
        ->set('categoryId', (string) $category->id)
        ->set('location', 'Ruang Rapat Lt. 2')
        ->set('description', 'WiFi sering putus saat rapat berlangsung.');
}

test('a guest can submit a report and is redirected to its page', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->set('guestName', 'Budi Santoso')
        ->set('guestContact', '081234567890')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('reports.show', 'WF001'));

    $report = Report::where('code', 'WF001')->firstOrFail();

    expect($report->guest_name)->toBe('Budi Santoso')
        ->and($report->reporter_user_id)->toBeNull();
});

test('a guest must provide a name and contact', function () {
    $category = Category::factory()->create();

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->call('save')
        ->assertHasErrors(['guestName' => 'required', 'guestContact' => 'required']);

    expect(Report::count())->toBe(0);
});

test('a logged in user reports under their own account', function () {
    $category = Category::factory()->create(['prefix' => 'KOM']);
    $user = User::factory()->create();

    $this->actingAs($user);

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('reports.show', 'KOM001'));

    $report = Report::where('code', 'KOM001')->firstOrFail();

    expect($report->reporter_user_id)->toEqual($user->id)
        ->and($report->guest_name)->toBeNull();
});

test('required fields are validated', function () {
    Livewire::test('pages::reports.create')
        ->call('save')
        ->assertHasErrors(['categoryId', 'location', 'description']);
});

test('changing the category clears the selected issue type', function () {
    $wifi = Category::factory()->create();
    $computer = Category::factory()->create();
    $type = IssueType::factory()->create(['category_id' => $wifi->id]);

    Livewire::test('pages::reports.create')
        ->set('categoryId', (string) $wifi->id)
        ->set('issueTypeId', (string) $type->id)
        ->set('categoryId', (string) $computer->id)
        ->assertSet('issueTypeId', '');
});

test('an issue type from another category is rejected', function () {
    $category = Category::factory()->create();
    $otherType = IssueType::factory()->create();

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->set('issueTypeId', (string) $otherType->id)
        ->set('guestName', 'Budi')
        ->set('guestContact', '0812')
        ->call('save')
        ->assertHasErrors('issueTypeId');
});

test('a filled honeypot field creates nothing', function () {
    $category = Category::factory()->create();

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->set('guestName', 'Bot')
        ->set('guestContact', 'bot@example.com')
        ->set('website', 'https://spam.example.com')
        ->call('save')
        ->assertRedirect(route('home'));

    expect(Report::count())->toBe(0);
});

test('guests are rate limited per hour', function () {
    config(['silapor.guest_reports_per_hour' => 2]);

    $category = Category::factory()->create();

    foreach (range(1, 2) as $ignored) {
        fillReportForm(Livewire::test('pages::reports.create'), $category)
            ->set('guestName', 'Budi')
            ->set('guestContact', '0812')
            ->call('save')
            ->assertHasNoErrors();
    }

    fillReportForm(Livewire::test('pages::reports.create'), $category)
        ->set('guestName', 'Budi')
        ->set('guestContact', '0812')
        ->call('save')
        ->assertHasErrors('rateLimit');

    expect(Report::count())->toBe(2);
});
