<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Halaman publik (dapat dibuka tanpa login)
Route::livewire('/', 'pages::home')->name('home');
Route::livewire('lapor', 'pages::reports.create')->name('reports.create');
Route::livewire('laporan', 'pages::reports.index')->name('reports.index');
Route::livewire('laporan/{report:code}', 'pages::reports.show')->name('reports.show');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::livewire('laporan-saya', 'pages::reports.mine')->name('reports.mine');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');
        Route::livewire('laporan', 'pages::admin.reports.index')->name('reports.index');
        Route::livewire('statistik', 'pages::admin.statistics')->name('statistics');
        Route::livewire('kategori', 'pages::admin.categories.index')->name('categories.index');
        Route::livewire('kategori/{category}', 'pages::admin.categories.show')->name('categories.show');
        Route::livewire('pengguna', 'pages::admin.users')->name('users.index');
    });

require __DIR__.'/settings.php';
