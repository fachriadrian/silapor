<?php

use App\Actions\Categories\SaveIssueType;
use App\Actions\Categories\SetIssueTypeActive;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Jenis Kendala')] class extends Component {
    public Category $category;

    public string $newName = '';

    public ?int $editingId = null;

    public string $editName = '';

    public ?string $notice = null;

    public function mount(Category $category): void
    {
        $this->category = $category;
    }

    #[Computed]
    public function issueTypes(): Collection
    {
        return IssueType::query()
            ->withCount('reports')
            ->where('category_id', $this->category->id)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function add(): void
    {
        $user = $this->admin();
        $this->resetValidation();

        try {
            $issueType = app(SaveIssueType::class)($user, $this->category, null, $this->newName);
        } catch (ValidationException $e) {
            $this->fillErrors($e, ['name' => 'newName']);

            return;
        }

        $this->reset('newName');
        $this->notice = "Jenis kendala {$issueType->name} ditambahkan.";
    }

    public function edit(int $id): void
    {
        $this->admin();
        $issueType = $this->findIssueType($id);

        $this->resetValidation();
        $this->editingId = $issueType->id;
        $this->editName = $issueType->name;
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'editName']);
    }

    public function saveEdit(): void
    {
        $user = $this->admin();
        $issueType = $this->findIssueType((int) $this->editingId);
        $this->resetValidation();

        try {
            $issueType = app(SaveIssueType::class)($user, $this->category, $issueType, $this->editName);
        } catch (ValidationException $e) {
            $this->fillErrors($e, ['name' => 'editName']);

            return;
        }

        $this->reset(['editingId', 'editName']);
        $this->notice = "Jenis kendala {$issueType->name} diperbarui.";
    }

    public function toggleActive(int $id): void
    {
        $user = $this->admin();
        $issueType = $this->findIssueType($id);

        $issueType = app(SetIssueTypeActive::class)($user, $issueType, ! $issueType->is_active);

        $this->notice = $issueType->is_active
            ? "Jenis kendala {$issueType->name} diaktifkan."
            : "Jenis kendala {$issueType->name} dinonaktifkan.";
    }

    protected function findIssueType(int $id): IssueType
    {
        return IssueType::query()
            ->where('category_id', $this->category->id)
            ->findOrFail($id);
    }

    protected function admin(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }

    /**
     * @param  array<string, string>  $map
     */
    protected function fillErrors(ValidationException $e, array $map = []): void
    {
        foreach ($e->errors() as $key => $messages) {
            $this->addError($map[$key] ?? $key, $messages[0]);
        }
    }
};
?>

<div class="space-y-6">
    <x-admin-nav active="kategori" />

    <div class="space-y-2">
        <flux:button :href="route('admin.categories.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            Semua kategori
        </flux:button>

        <div class="flex flex-wrap items-center gap-2">
            <flux:heading size="xl" level="1">Jenis kendala: {{ $category->name }}</flux:heading>
            <flux:badge size="sm">{{ $category->prefix }}</flux:badge>
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Jenis kendala bersifat opsional bagi pelapor dan membantu analisis di halaman statistik
            (mis. WiFi: Lambat, Putus-putus, Tidak bisa terhubung).
        </p>
    </div>

    @if ($notice)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            {{ $notice }}
        </div>
    @endif

    <form wire:submit="add" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-[1fr_auto] sm:items-start dark:border-zinc-700 dark:bg-zinc-800">
        <flux:input wire:model="newName" label="Jenis kendala baru" placeholder="Contoh: Putus-putus" />
        <div class="sm:pt-6">
            <flux:button type="submit" variant="primary" icon="plus">Tambah</flux:button>
        </div>
    </form>

    <div class="space-y-3">
        @forelse ($this->issueTypes as $type)
            <div wire:key="type-{{ $type->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                @if ($editingId === $type->id)
                    <form wire:submit="saveEdit" class="grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-start">
                        <flux:input wire:model="editName" label="Nama" />
                        <div class="sm:pt-6">
                            <flux:button type="submit" variant="primary">Simpan</flux:button>
                        </div>
                        <div class="sm:pt-6">
                            <flux:button type="button" wire:click="cancelEdit" variant="ghost">Batal</flux:button>
                        </div>
                    </form>
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $type->name }}</span>
                                @unless ($type->is_active)
                                    <flux:badge size="sm" color="zinc">Nonaktif</flux:badge>
                                @endunless
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $type->reports_count }} laporan</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" wire:click="edit({{ $type->id }})">Ubah</flux:button>
                            <flux:button
                                size="sm"
                                wire:click="toggleActive({{ $type->id }})"
                                :variant="$type->is_active ? 'ghost' : 'primary'"
                            >
                                {{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                Belum ada jenis kendala untuk kategori ini.
            </p>
        @endforelse
    </div>
</div>
