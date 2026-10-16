<?php

use App\Support\ReportStatistics;
use App\Support\StatisticsPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Statistik Laporan')] class extends Component {
    #[Url]
    public string $period = '30';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public bool $includeCancelled = false;

    #[Url]
    public ?int $threshold = null;

    public function mount(): void
    {
        $this->threshold ??= (int) config('silapor.recurring_threshold', 3);
    }

    #[Computed]
    public function periodRange(): StatisticsPeriod
    {
        return StatisticsPeriod::make(
            $this->period,
            $this->from !== '' ? $this->from : null,
            $this->to !== '' ? $this->to : null,
        );
    }

    #[Computed]
    public function stats(): ReportStatistics
    {
        return new ReportStatistics($this->periodRange, $this->includeCancelled);
    }

    #[Computed]
    public function effectiveThreshold(): int
    {
        return max(2, min(20, $this->threshold ?? 3));
    }

    #[Computed]
    public function windowDays(): int
    {
        return max(1, (int) config('silapor.recurring_window_days', 30));
    }
};
?>

@php
    $summary = $this->stats->summary();
    $byCategory = $this->stats->byCategory();
    $resolution = $this->stats->resolutionByCategory();
    $recurring = $this->stats->recurring($this->effectiveThreshold, $this->windowDays);
    $trend = $this->stats->monthlyTrend();
    $maxCount = collect($byCategory)->max('count') ?: 1;

    $duration = function (?float $hours): string {
        if ($hours === null) {
            return '–';
        }

        return $hours < 48
            ? number_format($hours, 1, ',', '.').' jam'
            : number_format($hours / 24, 1, ',', '.').' hari';
    };

    $heat = [
        'bg-zinc-50 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500',
        'bg-sky-100 dark:bg-sky-950',
        'bg-sky-200 dark:bg-sky-900',
        'bg-sky-300 dark:bg-sky-800',
        'bg-sky-400 text-white dark:bg-sky-700',
    ];
@endphp

<div class="space-y-6">
    <x-admin-nav active="statistik" />

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Statistik Laporan</flux:heading>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Periode: {{ $this->periodRange->label }}</p>
        </div>
    </div>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="period" label="Periode">
            <flux:select.option value="7">7 hari terakhir</flux:select.option>
            <flux:select.option value="30">30 hari terakhir</flux:select.option>
            <flux:select.option value="90">90 hari terakhir</flux:select.option>
            <flux:select.option value="year">Tahun ini</flux:select.option>
            <flux:select.option value="custom">Rentang kustom</flux:select.option>
        </flux:select>

        @if ($period === 'custom')
            <flux:input type="date" wire:model.live="from" label="Dari tanggal" />
            <flux:input type="date" wire:model.live="to" label="Sampai tanggal" />
        @endif

        <div class="flex items-end pb-2">
            <flux:checkbox wire:model.live="includeCancelled" label="Sertakan laporan dibatalkan" />
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Total laporan</p>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Masih aktif</p>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['active'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Selesai</p>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['completed'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Dibatalkan</p>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['cancelled'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Rata-rata waktu penyelesaian</p>
            <p class="mt-1 text-2xl font-semibold">{{ $duration($summary['average_resolution_hours']) }}</p>
        </div>
    </section>

    <section class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="2">Laporan per jenis kendala</flux:heading>

        @forelse ($byCategory as $row)
            <div wire:key="cat-{{ $row['id'] }}" class="space-y-1">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium">{{ $row['name'] }}</span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ $row['count'] }} laporan · {{ $row['percent'] }}%</span>
                </div>
                <div class="h-2 rounded bg-zinc-100 dark:bg-zinc-700">
                    <div class="h-2 rounded bg-sky-500" style="width: {{ round($row['count'] / $maxCount * 100) }}%"></div>
                </div>
            </div>
        @empty
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada laporan pada periode ini.</p>
        @endforelse
    </section>

    <section class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <flux:heading size="lg" level="2">Kendala berulang</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Kombinasi kategori, jenis kendala, dan lokasi yang dilaporkan lebih dari sekali.
                    Ditandai "Berulang" bila muncul {{ $this->effectiveThreshold }} kali atau lebih dalam {{ $this->windowDays }} hari.
                </p>
            </div>

            <div class="w-36">
                <flux:input type="number" min="2" max="20" wire:model.live.debounce.500ms="threshold" label="Ambang (kali)" />
            </div>
        </div>

        @if (count($recurring) > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pe-3 font-medium">Kategori / jenis kendala</th>
                            <th class="py-2 pe-3 font-medium">Lokasi</th>
                            <th class="py-2 pe-3 text-right font-medium">Jumlah</th>
                            <th class="py-2 pe-3 font-medium">Terakhir</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($recurring as $row)
                            <tr wire:key="rec-{{ $loop->index }}">
                                <td class="py-2 pe-3">
                                    <span class="font-medium">{{ $row['category'] }}</span>
                                    @if ($row['issue_type'])
                                        <span class="text-zinc-500 dark:text-zinc-400"> · {{ $row['issue_type'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pe-3">{{ $row['location'] }}</td>
                                <td class="py-2 pe-3 text-right font-semibold">{{ $row['count'] }}</td>
                                <td class="py-2 pe-3"><x-local-time :value="$row['last_at']" /></td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        @if ($row['recurring'])
                                            <flux:badge color="red" size="sm">Berulang</flux:badge>
                                        @endif
                                        <a
                                            href="{{ route('admin.reports.index', ['category' => $row['category_id'], 'search' => $row['location'], 'showArchived' => 1]) }}"
                                            class="text-xs underline"
                                        >
                                            Lihat laporan
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada kendala yang berulang pada periode ini.</p>
        @endif
    </section>

    <section class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="2">Rata-rata waktu penyelesaian per kategori</flux:heading>

        @forelse ($resolution as $row)
            <div wire:key="res-{{ $loop->index }}" class="flex items-center justify-between gap-3 text-sm">
                <span class="font-medium">{{ $row['name'] }}</span>
                <span class="text-zinc-500 dark:text-zinc-400">
                    {{ $duration($row['average_hours']) }} ({{ $row['count'] }} laporan selesai)
                </span>
            </div>
        @empty
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada laporan selesai pada periode ini.</p>
        @endforelse
    </section>

    <section class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <div>
            <flux:heading size="lg" level="2">Tren 6 bulan terakhir</flux:heading>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Jumlah laporan per kategori. Warna lebih pekat berarti lebih banyak.</p>
        </div>

        @if (count($trend['rows']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[34rem] text-sm">
                    <thead class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 pe-3 text-left font-medium">Kategori</th>
                            @foreach ($trend['months'] as $label)
                                <th class="px-1 py-2 text-center font-medium">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trend['rows'] as $row)
                            <tr wire:key="trend-{{ $row['id'] }}">
                                <td class="py-1 pe-3 font-medium">{{ $row['name'] }}</td>
                                @foreach ($row['counts'] as $count)
                                    @php
                                        $level = $count === 0 ? 0 : min(4, (int) ceil($count / max(1, $trend['max']) * 4));
                                    @endphp
                                    <td class="p-1">
                                        <div class="rounded px-2 py-1 text-center {{ $heat[$level] }}">{{ $count }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Belum ada laporan dalam 6 bulan terakhir.</p>
        @endif
    </section>
</div>
