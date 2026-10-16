<?php

use App\Actions\Categories\SaveCategory;
use App\Actions\Categories\SetCategoryActive;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Kategori Laporan')] class extends Component {
    // Form tambah
    public string $name = '';

    public string $prefix = '';

    // Form ubah
    public ?int $editingId = null;

    public string $editName = '';

    public string $editPrefix = '';

    public ?string $notice = null;

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->withCount(['reports', 'issueTypes'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function add(): void
    {
        $user = $this->admin();
        $this->resetValidation();

        try {
            $category = app(SaveCategory::class)($user, null, $this->name, $this->prefix);
        } catch (ValidationException $e) {
            $this->fillErrors($e);

            return;
        }

        $this->reset(['name', 'prefix']);
        $this->notice = "Kategori {$category->name} ({$category->prefix}) ditambahkan.";
    }

    public function edit(int $id): void
    {
        $this->admin();
        $category = Category::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $category->id;
        $this->editName = $category->name;
        $this->editPrefix = $category->prefix;
    }

    public function cancelEdit(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'editName', 'editPrefix']);
    }

    public function saveEdit(): void
    {
        $user = $this->admin();
        $category = Category::query()->findOrFail($this->editingId);
        $this->resetValidation();

        try {
            $category = app(SaveCategory::class)($user, $category, $this->editName, $this->editPrefix);
        } catch (ValidationException $e) {
            $this->fillErrors($e, ['name' => 'editName', 'prefix' => 'editPrefix']);

            return;
        }

        $this->reset(['editingId', 'editName', 'editPrefix']);
        $this->notice = "Kategori {$category->name} diperbarui.";
    }

    public function toggleActive(int $id): void
    {
        $user = $this->admin();
        $category = Category::query()->findOrFail($id);
        $this->resetValidation();

        try {
            $category = app(SetCategoryActive::class)($user, $category, ! $category->is_active);
        } catch (ValidationException $e) {
            $this->fillErrors($e, ['is_active' => 'toggle']);

            return;
        }

        $this->notice = $category->is_active
            ? "Kategori {$category->name} diaktifkan."
            : "Kategori {$category->name} dinonaktifkan.";
    }

    protected function admin(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $user;
    }

    /**
     * Memindahkan pesan galat dari aksi ke kolom form yang sesuai.
     *
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

    <div class="space-y-1">
        <flux:heading size="xl" level="1">Kategori Laporan</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Kategori tidak dihapus, hanya dinonaktifkan, supaya laporan lama tetap utuh.
            Prefix menjadi awalan kode laporan (mis. WF001) dan terkunci setelah dipakai.
        </p>
    </div>

    @if ($notice)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            {{ $notice }}
        </div>
    @endif

    @error('toggle')
        <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>
    @enderror

    <form wire:submit="add" class="grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-[1fr_8rem_auto] sm:items-start dark:border-zinc-700 dark:bg-zinc-800">
        <flux:input wire:model="name" label="Nama kategori baru" placeholder="Contoh: Perangkat Audio" />
        <flux:input wire:model="prefix" label="Prefix" placeholder="PRA" maxlength="4" />
        <div class="sm:pt-6">
            <flux:button type="submit" variant="primary" icon="plus">Tambah</flux:button>
        </div>
    </form>

    <div class="space-y-3">
        @foreach ($this->categories as $category)
            <div wire:key="category-{{ $category->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                @if ($editingId === $category->id)
                    <form wire:submit="saveEdit" class="grid gap-3 sm:grid-cols-[1fr_8rem_auto_auto] sm:items-start">
                        <flux:input wire:model="editName" label="Nama" />
                        <flux:input wire:model="editPrefix" label="Prefix" maxlength="4" :disabled="$category->reports_count > 0" />
                        <div class="sm:pt-6">
                            <flux:button type="submit" variant="primary">Simpan</flux:button>
                        </div>
                        <div class="sm:pt-6">
                            <flux:button type="button" wire:click="cancelEdit" variant="ghost">Batal</flux:button>
                        </div>
                    </form>

                    @if ($category->reports_count > 0)
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            Prefix terkunci karena sudah dipakai {{ $category->reports_count }} laporan.
                        </p>
                    @endif
                @else
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $category->name }}</span>
                                <flux:badge size="sm">{{ $category->prefix }}</flux:badge>
                                @unless ($category->is_active)
                                    <flux:badge size="sm" color="zinc">Nonaktif</flux:badge>
                                @endunless
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $category->reports_count }} laporan · {{ $category->issue_types_count }} jenis kendala
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button size="sm" wire:click="edit({{ $category->id }})">Ubah</flux:button>
                            <flux:button size="sm" :href="route('admin.categories.show', $category)" wire:navigate>Jenis kendala</flux:button>
                            <flux:button
                                size="sm"
                                wire:click="toggleActive({{ $category->id }})"
                                :variant="$category->is_active ? 'ghost' : 'primary'"
                            >
                                {{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
