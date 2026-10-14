<?php

use App\Models\Report;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Detail Laporan')] class extends Component {
    public Report $report;

    public bool $justCreated = false;

    public function mount(Report $report): void
    {
        $this->report = $report->load(['category', 'issueType', 'reporter', 'logs']);

        // Banner "laporan terkirim" hanya muncul sekali, tepat setelah laporan dibuat.
        // pull() mengambil sekaligus menghapus nilainya dari sesi.
        $this->justCreated = session()->pull('report_created') === $report->code;
    }
};
?>

<div class="space-y-6">
    <div>
        <flux:button :href="route('reports.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            Daftar laporan
        </flux:button>
    </div>

    @if ($justCreated)
        <div
            x-data="{ copied: false }"
            class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100"
        >
            <p class="font-medium">Laporan terkirim. Simpan kode laporan Anda untuk memantau progresnya:</p>

            <div class="mt-2 flex flex-wrap items-center gap-3">
                <span class="font-mono text-2xl font-bold">{{ $report->display_code }}</span>

                <flux:button
                    size="sm"
                    icon="clipboard-document"
                    x-on:click="navigator.clipboard.writeText('{{ $report->display_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-text="copied ? 'Tersalin' : 'Salin kode'"></span>
                </flux:button>
            </div>
        </div>
    @endif

    <section class="space-y-4 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-mono text-lg font-semibold">{{ $report->display_code }}</span>

            <div class="flex flex-wrap items-center gap-2">
                <x-urgency-badge :urgency="$report->urgency" />
                <x-report-status-badge :status="$report->status" />
            </div>
        </div>

        <flux:heading size="xl" level="1">{{ $report->title }}</flux:heading>

        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">Kategori</dt>
                <dd class="font-medium">{{ $report->category->name }}</dd>
            </div>

            @if ($report->issueType)
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Jenis kendala</dt>
                    <dd class="font-medium">{{ $report->issueType->name }}</dd>
                </div>
            @endif

            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">Lokasi</dt>
                <dd class="font-medium">{{ $report->location }}</dd>
            </div>

            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">Dilaporkan oleh</dt>
                <dd class="font-medium">{{ $report->reporterNameFor(auth()->user()) }}</dd>
            </div>

            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">Dilaporkan pada</dt>
                <dd class="font-medium"><x-local-time :value="$report->created_at" /></dd>
            </div>

            <div>
                <dt class="text-zinc-500 dark:text-zinc-400">Pembaruan terakhir</dt>
                <dd class="font-medium"><x-local-time :value="$report->updated_at" /></dd>
            </div>
        </dl>

        <div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Deskripsi</p>
            <p class="mt-1 whitespace-pre-line">{{ $report->description }}</p>
        </div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">Log Progres</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Catatan terbaru ada di bagian atas.</p>

        <ol class="space-y-5 border-s-2 border-zinc-200 ps-5 dark:border-zinc-700">
            @foreach ($report->logs->reverse() as $log)
                <li wire:key="log-{{ $log->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $log->event_type->label() }}</span>

                        @if ($log->from_status && $log->to_status)
                            <x-report-status-badge :status="$log->from_status" />
                            <flux:icon.arrow-right class="size-3" />
                            <x-report-status-badge :status="$log->to_status" />
                        @elseif ($log->to_status)
                            <x-report-status-badge :status="$log->to_status" />
                        @endif
                    </div>

                    @if ($log->note)
                        <p class="mt-1 whitespace-pre-line text-sm">{{ $log->note }}</p>
                    @endif

                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $log->actorNameFor(auth()->user(), $report) }} ({{ $log->actor_type->label() }}) ·
                        <x-local-time :value="$log->created_at" />
                    </p>
                </li>
            @endforeach
        </ol>
    </section>
</div>