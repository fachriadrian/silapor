<?php

use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
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

new #[Layout('layouts::public'), Title('Kelola Laporan')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $urgency = '';

    #[Url]
    public string $sort = 'terbaru';

    #[Url]
    public bool $showArchived = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'urgency', 'sort', 'showArchived'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        $query = Report::query()
            ->with(['category', 'reporter'])
            ->search($this->search)
            ->when(
                ReportStatus::tryFrom($this->status) !== null,
                // Status dipilih eksplisit: tampilkan apa adanya, termasuk laporan selesai.
                fn ($q) => $q->where('status', $this->status),
                // Tanpa filter status: laporan selesai/dibatalkan disembunyikan (DSH-04).
                fn ($q) => $q->when(! $this->showArchived, fn ($q) => $q->active()),
            )
            ->when(
                $this->category !== '',
                fn ($q) => $q->where('category_id', (int) $this->category),
            )
            ->when(
                ReportUrgency::tryFrom($this->urgency) !== null,
                fn ($q) => $q->where('urgency', $this->urgency),
            );

        $query = match ($this->sort) {
            'urgensi' => $query
                ->orderByRaw("case urgency when 'tinggi' then 3 when 'sedang' then 2 else 1 end desc")
                ->latestFirst(),
            'lama' => $query->orderBy('updated_at')->orderBy('id'),
            default => $query->latestFirst(),
        };

        return $query->paginate(15);
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->orderBy('name')->get();
    }
};
?>

<div class="space-y-4">
    <x-admin-nav active="laporan" />

    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Kelola Laporan</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->reports->total() }} laporan</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="sm:col-span-2 lg:col-span-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari kode, judul, atau lokasi"
            />
        </div>

        <flux:select wire:model.live="status">
            <flux:select.option value="">Semua status aktif</flux:select.option>
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

        <flux:select wire:model.live="urgency">
            <flux:select.option value="">Semua urgensi</flux:select.option>
            @foreach (\App\Enums\ReportUrgency::options() as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="sort">
            <flux:select.option value="terbaru">Urut: Terbaru</flux:select.option>
            <flux:select.option value="urgensi">Urut: Urgensi tertinggi</flux:select.option>
            <flux:select.option value="lama">Urut: Pembaruan terlama</flux:select.option>
        </flux:select>

        <div class="flex items-center">
            <flux:checkbox wire:model.live="showArchived" label="Tampilkan yang selesai dan dibatalkan" />
        </div>
    </div>

    <div class="space-y-3">
        @forelse ($this->reports as $report)
            <div wire:key="admin-report-{{ $report->id }}">
                <x-report-card :report="$report" :viewer="auth()->user()" :show-urgency="true" />
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
