<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use Livewire\Livewire;

test('similar active reports are suggested while filling the form', function () {
    $category = Category::factory()->create(['prefix' => 'WF']);
    $other = Category::factory()->create();

    Report::factory()->create([
        'code' => 'WF001',
        'title' => 'WiFi putus di lantai dua',
        'category_id' => $category->id,
        'location' => 'Ruang Rapat Lantai 2',
    ]);
    Report::factory()->withStatus(ReportStatus::Completed)->create([
        'title' => 'Laporan lama selesai',
        'category_id' => $category->id,
        'location' => 'Ruang Rapat Lantai 2',
    ]);
    Report::factory()->create([
        'title' => 'Laporan kategori lain',
        'category_id' => $other->id,
        'location' => 'Ruang Rapat Lantai 2',
    ]);

    Livewire::test('pages::reports.create')
        ->set('categoryId', (string) $category->id)
        ->set('location', 'Lantai 2')
        ->assertSee('Sudah ada laporan aktif serupa')
        ->assertSee('WiFi putus di lantai dua')
        ->assertDontSee('Laporan lama selesai')
        ->assertDontSee('Laporan kategori lain');
});

test('no suggestion is shown without a category or with a very short location', function () {
    $category = Category::factory()->create();

    Report::factory()->create([
        'category_id' => $category->id,
        'location' => 'Ruang Rapat Lantai 2',
    ]);

    Livewire::test('pages::reports.create')
        ->set('location', 'Lantai 2')
        ->assertDontSee('Sudah ada laporan aktif serupa')
        ->set('categoryId', (string) $category->id)
        ->set('location', 'Ru')
        ->assertDontSee('Sudah ada laporan aktif serupa');
});

test('no suggestion is shown when nothing matches', function () {
    $category = Category::factory()->create();

    Report::factory()->create([
        'category_id' => $category->id,
        'location' => 'Gudang',
    ]);

    Livewire::test('pages::reports.create')
        ->set('categoryId', (string) $category->id)
        ->set('location', 'Ruang Server')
        ->assertDontSee('Sudah ada laporan aktif serupa');
});
