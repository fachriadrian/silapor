<?php

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Dashboard Admin')] class extends Component {
    /**
     * Jumlah laporan aktif per status dan jumlah yang berurgensi tinggi.
     *
     * @return array{baru: int, diproses: int, ditunda: int, urgen: int}
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = Report::query()
            ->active()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'baru' => (int) ($byStatus[ReportStatus::New->value] ?? 0),
            'diproses' => (int) ($byStatus[ReportStatus::InProgress->value] ?? 0),
            'ditunda' => (int) ($byStatus[ReportStatus::OnHold->value] ?? 0),
            'urgen' => Report::query()->active()->where('urgency', ReportUrgency::High->value)->count(),
        ];
    }

    /**
     * Laporan baru yang belum ditangani, terbaru di atas (maksimal 10).
     */
    #[Computed]
    public function latestNew(): Collection
    {
        return Report::query()
            ->with(['category', 'reporter'])
            ->where('status', ReportStatus::New->value)
            ->latestFirst()
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function staleDays(): int
    {
        return (int) config('silapor.stale_after_days', 3);
    }

    #[Computed]
    public function staleCount(): int
    {
        return $this->staleQuery()->count();
    }

    /**
     * Laporan aktif yang paling lama tidak diperbarui.
     */
    #[Computed]
    public function stale(): Collection
    {
        return $this->staleQuery()
            ->with(['category', 'reporter'])
            ->orderBy('updated_at')
            ->limit(5)
            ->get();
    }

    protected function staleQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Report::query()
            ->active()
            ->where('updated_at', '<', now()->subDays($this->staleDays));
    }
};
?>

<div class="space-y-6">
    <x-admin-nav active="dashboard" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Dashboard Admin</flux:heading>
        <flux:button :href="route('admin.reports.index')" variant="primary">Kelola Laporan</flux:button>
    </div>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="{{ route('admin.reports.index', ['status' => 'baru']) }}" class="rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Baru</p>
            <p class="mt-1 text-3xl font-semibold" data-test="count-baru">{{ $this->counts['baru'] }}</p>
        </a>

        <a href="{{ route('admin.reports.index', ['status' => 'diproses']) }}" class="rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Diproses</p>
            <p class="mt-1 text-3xl font-semibold" data-test="count-diproses">{{ $this->counts['diproses'] }}</p>
        </a>

        <a href="{{ route('admin.reports.index', ['status' => 'ditunda']) }}" class="rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Ditunda</p>
            <p class="mt-1 text-3xl font-semibold" data-test="count-ditunda">{{ $this->counts['ditunda'] }}</p>
        </a>

        <a href="{{ route('admin.reports.index', ['urgency' => 'tinggi']) }}" class="rounded-xl border border-red-200 bg-red-50 p-4 transition hover:border-red-300 dark:border-red-900 dark:bg-red-950/40 dark:hover:border-red-800">
            <p class="text-sm text-red-700 dark:text-red-300">Urgensi tinggi (aktif)</p>
            <p class="mt-1 text-3xl font-semibold text-red-700 dark:text-red-300" data-test="count-urgen">{{ $this->counts['urgen'] }}</p>
        </a>
    </section>

    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg" level="2">Laporan baru masuk</flux:heading>

            @if ($this->counts['baru'] > $this->latestNew->count())
                <flux:button :href="route('admin.reports.index', ['status' => 'baru'])" size="sm" variant="ghost">
                    Lihat semua ({{ $this->counts['baru'] }})
                </flux:button>
            @endif
        </div>

        @forelse ($this->latestNew as $report)
            <div wire:key="new-{{ $report->id }}">
                <x-report-card :report="$report" :viewer="auth()->user()" :show-urgency="true" />
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                Tidak ada laporan baru. Semua laporan sudah ditangani.
            </p>
        @endforelse
    </section>

    @if ($this->staleCount > 0)
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <flux:heading size="lg" level="2">Perlu perhatian</flux:heading>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $this->staleCount }} laporan aktif belum diperbarui lebih dari {{ $this->staleDays }} hari.
                    </p>
                </div>

                <flux:button :href="route('admin.reports.index', ['sort' => 'lama'])" size="sm" variant="ghost">
                    Lihat semua
                </flux:button>
            </div>

            @foreach ($this->stale as $report)
                <div wire:key="stale-{{ $report->id }}">
                    <x-report-card :report="$report" :viewer="auth()->user()" :show-urgency="true" />
                </div>
            @endforeach
        </section>
    @endif
</div>
