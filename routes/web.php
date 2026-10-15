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
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::livewire('laporan', 'pages::admin.reports.index')->name('reports.index');
    });

require __DIR__.'/settings.php';
