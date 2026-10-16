<?php

use App\Actions\Reports\AddReportNote;
use App\Actions\Reports\CancelReport;
use App\Actions\Reports\ChangeReportStatus;
use App\Actions\Reports\ChangeReportUrgency;
use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Models\Report;
use App\Models\User;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Detail Laporan')] class extends Component {
    public Report $report;

    public bool $justCreated = false;

    public ?string $notice = null;

    // Pembatalan (pemilik laporan atau admin)
    public string $cancelReason = '';

    // Panel admin
    public string $newStatus = '';

    public string $statusNote = '';

    public string $progressNote = '';

    public string $urgencyValue = '';

    public function mount(Report $report): void
    {
        $this->report = $report->load(['category', 'issueType', 'reporter', 'logs', 'attachments']);
        $this->urgencyValue = $report->urgency->value;

        // Banner "laporan terkirim" hanya muncul sekali, tepat setelah laporan dibuat.
        // pull() mengambil sekaligus menghapus nilainya dari sesi.
        $this->justCreated = session()->pull('report_created') === $report->code;
    }

    public function cancel(): void
    {
        $user = Auth::user();

        // Pemilik laporan atau admin, selama laporan belum Selesai/Dibatalkan.
        abort_unless($user instanceof User && Gate::forUser($user)->allows('cancel', $this->report), 403);

        $this->validate([
            'cancelReason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->report = app(CancelReport::class)($this->report, $user, $this->cancelReason);
        } catch (AuthorizationException) {
            // Status berubah di antara saat halaman dibuka dan tombol ditekan.
            $this->report->refresh();
            $this->addError('cancelReason', 'Laporan ini sudah tidak dapat dibatalkan.');

            return;
        }

        $this->reloadReport();
        $this->cancelReason = '';
        $this->notice = 'Laporan telah dibatalkan. Pembatalan tercatat di log progres.';

        Flux::modal('cancel-report')->close();
    }

    public function changeStatus(): void
    {
        $user = $this->adminUser();

        $this->validate([
            'newStatus' => ['required', Rule::enum(ReportStatus::class)],
            'statusNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = ReportStatus::from($this->newStatus);

        try {
            $this->report = app(ChangeReportStatus::class)($this->report, $target, $user, $this->statusNote);
        } catch (ValidationException $e) {
            // Pesan dari aksi memakai kunci 'status' dan 'note'; arahkan ke kolom form ini.
            $errors = $e->errors();

            if (isset($errors['status'])) {
                $this->addError('newStatus', $errors['status'][0]);
            }

            if (isset($errors['note'])) {
                $this->addError('statusNote', $errors['note'][0]);
            }

            return;
        }

        $this->reloadReport();
        $this->newStatus = '';
        $this->statusNote = '';
        $this->notice = "Status laporan diubah menjadi {$target->label()}.";
    }

    public function addNote(): void
    {
        $user = $this->adminUser();

        $this->validate([
            'progressNote' => ['required', 'string', 'max:2000'],
        ]);

        app(AddReportNote::class)($this->report, $user, $this->progressNote);

        $this->reloadReport();
        $this->progressNote = '';
        $this->notice = 'Keterangan progres ditambahkan.';
    }

    public function changeUrgency(): void
    {
        $user = $this->adminUser();

        $this->validate([
            'urgencyValue' => ['required', Rule::enum(ReportUrgency::class)],
        ]);

        $this->report = app(ChangeReportUrgency::class)(
            $this->report,
            ReportUrgency::from($this->urgencyValue),
            $user,
        );

        $this->reloadReport();
        $this->notice = 'Urgensi laporan diperbarui.';
    }

    protected function adminUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && Gate::forUser($user)->allows('changeStatus', $this->report), 403);

        return $user;
    }

    protected function reloadReport(): void
    {
        $this->report->refresh()->load(['category', 'issueType', 'reporter', 'logs', 'attachments']);
        $this->urgencyValue = $this->report->urgency->value;
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

    @if ($notice)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100">
            {{ $notice }}
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

            @can('viewContact', $report)
                <div class="sm:col-span-2">
                    <dt class="text-zinc-500 dark:text-zinc-400">Kontak pelapor (hanya terlihat oleh Staf IT)</dt>
                    <dd class="font-medium">
                        @if ($report->reporter)
                            {{ $report->reporter->name }} · {{ $report->reporter->email }}@if ($report->reporter->unit) · {{ $report->reporter->unit }}@endif
                        @else
                            {{ $report->guest_name }} · {{ $report->guest_contact }} (tamu)
                        @endif
                    </dd>
                </div>
            @endcan
        </dl>

        <div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Deskripsi</p>
            <p class="mt-1 whitespace-pre-line">{{ $report->description }}</p>
        </div>

        @if ($report->attachments->isNotEmpty())
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Foto lampiran</p>

                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($report->attachments as $attachment)
                        <a href="{{ $attachment->url }}" target="_blank" rel="noopener" wire:key="photo-{{ $attachment->id }}">
                            <img
                                src="{{ $attachment->url }}"
                                alt="Foto lampiran {{ $loop->iteration }} untuk laporan {{ $report->display_code }}"
                                loading="lazy"
                                class="size-28 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700"
                            >
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @can('cancel', $report)
            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:modal.trigger name="cancel-report">
                    <flux:button variant="danger" icon="x-circle">Batalkan Laporan</flux:button>
                </flux:modal.trigger>
            </div>
        @endcan
    </section>

    @can('changeStatus', $report)
        @php
            $nextStatuses = collect($report->status->allowedTransitions())
                ->reject(fn ($status) => $status === \App\Enums\ReportStatus::Cancelled);
        @endphp

        <section class="space-y-6 rounded-xl border border-amber-200 bg-amber-50/50 p-5 dark:border-amber-900 dark:bg-amber-950/30">
            <flux:heading size="lg" level="2">Kelola Laporan (Staf IT)</flux:heading>

            @if ($nextStatuses->isNotEmpty())
                <form wire:submit="changeStatus" class="space-y-3">
                    <flux:select wire:model="newStatus" label="Ubah status">
                        <flux:select.option value="">Pilih status baru</flux:select.option>
                        @foreach ($nextStatuses as $status)
                            <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea
                        wire:model="statusNote"
                        label="Keterangan"
                        rows="2"
                        placeholder="Wajib untuk status Ditunda dan saat membuka kembali laporan."
                    />

                    <flux:button type="submit" variant="primary">Simpan status</flux:button>
                </form>
            @endif

            <form wire:submit="addNote" class="space-y-3">
                <flux:textarea
                    wire:model="progressNote"
                    label="Tambah keterangan progres"
                    rows="2"
                    placeholder="Contoh: Menunggu teknisi vendor, estimasi Kamis."
                />

                <flux:button type="submit">Tambah keterangan</flux:button>
            </form>

            @if (! $report->status->isFinal())
                <form wire:submit="changeUrgency" class="space-y-3">
                    <flux:select wire:model="urgencyValue" label="Tingkat urgensi">
                        @foreach (\App\Enums\ReportUrgency::cases() as $level)
                            <flux:select.option value="{{ $level->value }}">{{ $level->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit">Simpan urgensi</flux:button>
                </form>
            @endif
        </section>
    @endcan

    @can('cancel', $report)
        <flux:modal name="cancel-report" class="md:w-96">
            <form wire:submit="cancel" class="space-y-5">
                <div>
                    <flux:heading size="lg">Batalkan laporan?</flux:heading>
                    <flux:text class="mt-2">
                        Laporan tidak dihapus. Statusnya menjadi Dibatalkan dan alasan Anda tercatat di log progres.
                    </flux:text>
                </div>

                <flux:textarea wire:model="cancelReason" label="Alasan pembatalan" rows="3" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Kembali</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Ya, batalkan</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

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
