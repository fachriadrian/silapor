<?php

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public'), Title('Daftar Laporan')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->with(['category', 'reporter'])
            ->search($this->search)
            ->when(
                ReportStatus::tryFrom($this->status) !== null,
                fn ($query) => $query->where('status', $this->status),
            )
            ->when(
                $this->category !== '',
                fn ($query) => $query->where('category_id', (int) $this->category),
            )
            ->latestFirst()
            ->paginate(10);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Daftar Laporan</flux:heading>
        <flux:button :href="route('reports.create')" wire:navigate variant="primary" icon="plus">Buat Laporan</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Cari kode, judul, atau lokasi"
        />

        <flux:select wire:model.live="status">
            <flux:select.option value="">Semua status</flux:select.option>
            @foreach (\App\Enums\ReportStatus::options() as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="category">
            <flux:select.option value="">Semua kategori</flux:select.option>
            @foreach ($this->categories as $item)
                <flux:select.option value="{{ $item->id }}">{{ $item->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="space-y-3">
        @forelse ($this->reports as $report)
            <div wire:key="report-{{ $report->id }}">
                <x-report-card :report="$report" :viewer="auth()->user()" />
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                Tidak ada laporan yang cocok.
            </p>
        @endforelse
    </div>

    @if ($this->reports->hasPages())
        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button
                wire:click="previousPage"
                :disabled="$this->reports->onFirstPage()"
                size="sm"
                icon="chevron-left"
            >
                Sebelumnya
            </flux:button>

            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                Halaman {{ $this->reports->currentPage() }} dari {{ $this->reports->lastPage() }}
            </span>

            <flux:button
                wire:click="nextPage"
                :disabled="! $this->reports->hasMorePages()"
                size="sm"
                icon-trailing="chevron-right"
            >
                Berikutnya
            </flux:button>
        </div>
    @endif
</div>
