<?php

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Beranda')] class extends Component {
    #[Computed]
    public function activeCount(): int
    {
        return Report::active()->count();
    }

    #[Computed]
    public function completedCount(): int
    {
        return Report::where('status', ReportStatus::Completed->value)->count();
    }

    #[Computed]
    public function latest(): Collection
    {
        return Report::query()->with(['category', 'reporter'])->latestFirst()->limit(5)->get();
    }
};
?>

<div class="space-y-8">
    <section class="space-y-4 text-center">
        <flux:heading size="xl" level="1">Lapor kerusakan fasilitas kantor</flux:heading>
        <p class="mx-auto max-w-2xl text-zinc-600 dark:text-zinc-400">
            WiFi bermasalah, komputer rusak, atau printer macet? Laporkan di sini, tanpa perlu login,
            lalu pantau progres penanganannya dengan kode laporan Anda.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <flux:button :href="route('reports.create')" wire:navigate variant="primary" icon="plus">Buat Laporan</flux:button>
            <flux:button :href="route('reports.index')" wire:navigate>Lihat Semua Laporan</flux:button>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Laporan aktif</p>
            <p class="mt-1 text-3xl font-semibold">{{ $this->activeCount }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Laporan selesai</p>
            <p class="mt-1 text-3xl font-semibold">{{ $this->completedCount }}</p>
        </div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">Laporan terbaru</flux:heading>

        @forelse ($this->latest as $report)
            <div wire:key="latest-{{ $report->id }}">
                <x-report-card :report="$report" :viewer="auth()->user()" />
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                Belum ada laporan.
            </p>
        @endforelse
    </section>
</div>
