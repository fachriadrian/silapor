@props(['report', 'viewer' => null, 'showUrgency' => false])

<a
    href="{{ route('reports.show', $report->code) }}"
    wire:navigate
    class="block rounded-xl border border-zinc-200 bg-white p-4 transition hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600"
>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="font-mono text-sm font-semibold">{{ $report->display_code }}</span>

        <div class="flex flex-wrap items-center gap-2">
            @if ($showUrgency || $report->urgency === \App\Enums\ReportUrgency::High)
                <x-urgency-badge :urgency="$report->urgency" />
            @endif
            <x-report-status-badge :status="$report->status" />
        </div>
    </div>

    <p class="mt-2 font-medium">{{ $report->title }}</p>

    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
        {{ $report->category->name }} · {{ $report->location }} ·
        <x-local-time :value="$report->created_at" /> ·
        oleh {{ $report->reporterNameFor($viewer) }}
    </p>
</a>
