<?php

use App\Actions\Users\AdminResetUserPassword;
use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\SetUserActive;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::public'), Title('Pengguna')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = '';

    public ?string $notice = null;

    public ?string $temporaryPassword = null;

    public ?string $temporaryPasswordFor = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->search).'%';

        return User::query()
            ->withCount('reports')
            ->when($this->search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('unit', 'like', $like)
            ))
            ->when(UserRole::tryFrom($this->role) !== null, fn ($query) => $query->where('role', $this->role))
            ->when($this->status === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    public function toggleActive(int $id): void
    {
        [$actor, $target] = $this->resolve($id);

        $target = app(SetUserActive::class)($actor, $target, ! $target->is_active);

        $this->temporaryPassword = null;
        $this->notice = $target->is_active
            ? "Akun {$target->name} diaktifkan."
            : "Akun {$target->name} dinonaktifkan.";
    }

    public function toggleRole(int $id): void
    {
        [$actor, $target] = $this->resolve($id);

        $newRole = $target->role === UserRole::Admin ? UserRole::User : UserRole::Admin;
        $target = app(ChangeUserRole::class)($actor, $target, $newRole);

        $this->temporaryPassword = null;
        $this->notice = "{$target->name} sekarang berperan sebagai {$target->role->label()}.";
    }

    public function resetPassword(int $id): void
    {
        [$actor, $target] = $this->resolve($id);

        $this->temporaryPassword = app(AdminResetUserPassword::class)($actor, $target);
        $this->temporaryPasswordFor = $target->name;
        $this->notice = null;
    }

    public function dismissPassword(): void
    {
        $this->temporaryPassword = null;
        $this->temporaryPasswordFor = null;
    }

    /**
     * Memastikan pelaku adalah admin dan boleh mengelola akun target
     * (bukan akun sendiri).
     *
     * @return array{0: User, 1: User}
     */
    protected function resolve(int $id): array
    {
        $actor = Auth::user();

        abort_unless($actor instanceof User && $actor->isAdmin(), 403);

        $target = User::query()->findOrFail($id);

        abort_unless(Gate::forUser($actor)->allows('manage', $target), 403);

        return [$actor, $target];
    }
};
?>

<div class="space-y-6">
    <x-admin-nav active="pengguna" />

    <div class="space-y-1">
        <flux:heading size="xl" level="1">Pengguna</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Kelola peran dan status akun. Akun yang dinonaktifkan tidak bisa login dan laporannya tetap tersimpan.
            Akun Anda sendiri tidak bisa diubah dari halaman ini.
        </p>
    </div>

    @if ($temporaryPassword)
        <div
            x-data="{ copied: false }"
            class="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
        >
            <p class="font-medium">Password sementara untuk {{ $temporaryPasswordFor }}</p>

            <div class="flex flex-wrap items-center gap-3">
                <span class="font-mono text-xl font-bold">{{ $temporaryPassword }}</span>

                <flux:button
                    size="sm"
                    icon="clipboard-document"
                    x-on:click="navigator.clipboard.writeText('{{ $temporaryPassword }}'); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </flux:button>

                <flux:button size="sm" variant="ghost" wire:click="dismissPassword">Tutup</flux:button>
            </div>

            <p class="text-sm">
                Catat dan sampaikan ke pengguna sekarang. Password ini tidak akan ditampilkan lagi, dan sesi login lama
                pengguna tersebut sudah diakhiri. Minta pengguna menggantinya lewat menu Pengaturan setelah login.
            </p>
        </div>
    @endif

    @if ($notice)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            {{ $notice }}
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Cari nama, email, atau unit"
        />

        <flux:select wire:model.live="role">
            <flux:select.option value="">Semua peran</flux:select.option>
            <flux:select.option value="user">User</flux:select.option>
            <flux:select.option value="admin">Admin</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="status">
            <flux:select.option value="">Semua status</flux:select.option>
            <flux:select.option value="aktif">Aktif</flux:select.option>
            <flux:select.option value="nonaktif">Nonaktif</flux:select.option>
        </flux:select>
    </div>

    <div class="space-y-3">
        @forelse ($this->users as $user)
            <div wire:key="user-{{ $user->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $user->name }}</span>

                            @if ($user->id === auth()->id())
                                <flux:badge size="sm" color="sky">Anda</flux:badge>
                            @endif

                            <flux:badge size="sm" :color="$user->role === \App\Enums\UserRole::Admin ? 'purple' : 'zinc'">
                                {{ $user->role->label() }}
                            </flux:badge>

                            @unless ($user->is_active)
                                <flux:badge size="sm" color="red">Nonaktif</flux:badge>
                            @endunless
                        </div>

                        <p class="text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $user->email }}@if ($user->unit) · {{ $user->unit }}@endif
                        </p>

                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $user->reports_count }} laporan · bergabung <x-local-time :value="$user->created_at" />
                        </p>
                    </div>

                    @if ($user->id !== auth()->id())
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button
                                size="sm"
                                wire:click="toggleRole({{ $user->id }})"
                                wire:confirm="{{ $user->role === \App\Enums\UserRole::Admin ? 'Jadikan '.$user->name.' sebagai User biasa?' : 'Jadikan '.$user->name.' sebagai Admin (Staf IT)?' }}"
                            >
                                {{ $user->role === \App\Enums\UserRole::Admin ? 'Jadikan User' : 'Jadikan Admin' }}
                            </flux:button>

                            <flux:button
                                size="sm"
                                wire:click="resetPassword({{ $user->id }})"
                                wire:confirm="Buat password sementara baru untuk {{ $user->name }}? Password lama tidak berlaku lagi."
                            >
                                Reset password
                            </flux:button>

                            <flux:button
                                size="sm"
                                wire:click="toggleActive({{ $user->id }})"
                                :variant="$user->is_active ? 'ghost' : 'primary'"
                                wire:confirm="{{ $user->is_active ? 'Nonaktifkan akun '.$user->name.'?' : 'Aktifkan kembali akun '.$user->name.'?' }}"
                            >
                                {{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
                Tidak ada pengguna yang cocok.
            </p>
        @endforelse
    </div>

    @if ($this->users->hasPages())
        <div class="flex items-center justify-between gap-3 pt-2">
            <flux:button
                wire:click="previousPage"
                :disabled="$this->users->onFirstPage()"
                size="sm"
                icon="chevron-left"
            >
                Sebelumnya
            </flux:button>

            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                Halaman {{ $this->users->currentPage() }} dari {{ $this->users->lastPage() }}
            </span>

            <flux:button
                wire:click="nextPage"
                :disabled="! $this->users->hasMorePages()"
                size="sm"
                icon-trailing="chevron-right"
            >
                Berikutnya
            </flux:button>
        </div>
    @endif
</div>
