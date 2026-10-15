<?php

use App\Models\Report;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public'), Title('Laporan Saya')] class extends Component {
    use WithPagination;

    #[Url]
    public string $tab = 'aktif';

    public function mount(): void
    {
        if (! in_array($this->tab, ['aktif', 'riwayat', 'semua'], true)) {
            $this->tab = 'aktif';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['aktif', 'riwayat', 'semua'], true) ? $tab : 'aktif';
        $this->resetPage();
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return Report::query()
            ->with(['category', 'reporter'])
            ->where('reporter_user_id', auth()->id())
            ->when($this->tab === 'aktif', fn ($query) => $query->active())
            ->when($this->tab === 'riwayat', fn ($query) => $query->archived())
            ->latestFirst()
            ->paginate(10);
    }

    #[Computed]
    public function activeCount(): int
    {
        return Report::query()->where('reporter_user_id', auth()->id())->active()->count();
    }

    #[Computed]
    public function archivedCount(): int
    {
        return Report::query()->where('reporter_user_id', auth()->id())->archived()->count();
    }
};
?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:heading size="xl" level="1">Laporan Saya</flux:heading>
        <flux:button :href="route('reports.create')" wire:navigate variant="primary" icon="plus">Buat Laporan</flux:button>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button wire:click="setTab('aktif')" size="sm" :variant="$tab === 'aktif' ? 'primary' : 'outline'">
            Aktif ({{ $this->activeCount }})
        </flux:button>
        <flux:button wire:click="setTab('riwayat')" size="sm" :variant="$tab === 'riwayat' ? 'primary' : 'outline'">
            Riwayat ({{ $this->archivedCount }})
        </flux:button>
        <flux:button wire:click="setTab('semua')" size="sm" :variant="$tab === 'semua' ? 'primary' : 'outline'">
            Semua
        </flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($this->reports as $report)
            <div wire:key="mine-{{ $report->id }}">
                <x-report-card :report="$report" :viewer="auth()->user()" />
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                @if ($tab === 'aktif')
                    Tidak ada laporan aktif.
                @elseif ($tab === 'riwayat')
                    Belum ada laporan yang selesai atau dibatalkan.
                @else
                    Anda belum pernah membuat laporan.
                @endif
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
