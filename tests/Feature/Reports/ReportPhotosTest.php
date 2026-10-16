<?php

use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

function reportPhoto(string $name = 'foto.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 600, 400)->size(100);
}

test('photos are stored and attached to the new report', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    $report = app(CreateReport::class)(
        new NewReportData(
            categoryId: $category->id,
            description: 'WiFi putus-putus.',
            location: 'Lt. 1',
            photos: [reportPhoto('a.jpg'), reportPhoto('b.png')],
        ),
        User::factory()->create(),
    );

    $attachments = $report->attachments()->get();

    expect($attachments)->toHaveCount(2);

    foreach ($attachments as $attachment) {
        Storage::disk('public')->assertExists($attachment->path);
        expect($attachment->path)->toStartWith("reports/{$report->id}/")
            ->and($attachment->size_bytes)->toBeGreaterThan(0);
    }

    expect($attachments->first()->mime_type)->toBe('image/jpeg');
});

test('a report without photos has no attachments', function () {
    $category = Category::factory()->create();

    $report = app(CreateReport::class)(
        new NewReportData(categoryId: $category->id, description: 'Tanpa foto.', location: 'Lt. 1'),
        User::factory()->create(),
    );

    expect($report->attachments()->count())->toBe(0);
});

test('too many photos are rejected before anything is saved', function () {
    $category = Category::factory()->create();

    $photos = [reportPhoto('1.jpg'), reportPhoto('2.jpg'), reportPhoto('3.jpg'), reportPhoto('4.jpg')];

    expect(fn () => app(CreateReport::class)(
        new NewReportData(categoryId: $category->id, description: 'Terlalu banyak.', location: 'Lt. 1', photos: $photos),
        User::factory()->create(),
    ))->toThrow(InvalidArgumentException::class);

    expect(Report::count())->toBe(0);
});

test('a guest can attach photos and they appear on the detail page', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);

    Livewire::test('pages::reports.create')
        ->set('categoryId', (string) $category->id)
        ->set('location', 'Ruang Rapat')
        ->set('description', 'WiFi sering putus saat rapat berlangsung.')
        ->set('guestName', 'Budi Santoso')
        ->set('guestContact', '081234567890')
        ->set('photos', [reportPhoto('a.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $attachment = Report::where('code', 'WF001')->firstOrFail()->attachments()->firstOrFail();

    $this->get(route('reports.show', 'WF001'))
        ->assertOk()
        ->assertSee($attachment->url)
        ->assertSee('Foto lampiran');
});

test('an oversized photo is rejected', function () {
    Livewire::test('pages::reports.create')
        ->set('photos', [UploadedFile::fake()->image('besar.jpg')->size(3000)])
        ->assertHasErrors('photos.0');
});

test('files that are not images are rejected', function () {
    Livewire::test('pages::reports.create')
        ->set('photos', [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')])
        ->assertHasErrors('photos.0');
});

test('more than the allowed number of photos is rejected', function () {
    Livewire::test('pages::reports.create')
        ->set('photos', [reportPhoto('1.jpg'), reportPhoto('2.jpg'), reportPhoto('3.jpg'), reportPhoto('4.jpg')])
        ->assertHasErrors('photos');
});

test('a selected photo can be removed before sending', function () {
    $component = Livewire::test('pages::reports.create')
        ->set('photos', [reportPhoto('1.jpg'), reportPhoto('2.jpg')])
        ->call('removePhoto', 0);

    expect($component->get('photos'))->toHaveCount(1);
});
