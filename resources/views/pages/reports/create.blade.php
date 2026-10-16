<?php

use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Enums\ReportUrgency;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::public'), Title('Buat Laporan')] class extends Component {
    use WithFileUploads;

    public string $categoryId = '';

    public string $issueTypeId = '';

    public string $location = '';

    public string $reportTitle = '';

    public string $description = '';

    public string $urgency = 'sedang';

    public string $guestName = '';

    public string $guestContact = '';

    /** @var array<int, mixed> */
    public array $photos = [];

    // Honeypot: tidak terlihat oleh manusia, tetapi biasanya diisi oleh bot.
    public string $website = '';

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function issueTypes(): Collection
    {
        if ($this->categoryId === '') {
            return new Collection;
        }

        return IssueType::query()
            ->active()
            ->where('category_id', (int) $this->categoryId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Laporan aktif di kategori yang sama dengan lokasi yang mirip, untuk
     * mencegah laporan ganda (RPT-08).
     */
    #[Computed]
    public function similarReports(): Collection
    {
        $location = trim($this->location);

        if ($this->categoryId === '' || mb_strlen($location) < 3) {
            return new Collection;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $location).'%';

        return Report::query()
            ->active()
            ->where('category_id', (int) $this->categoryId)
            ->where('location', 'like', $like)
            ->latestFirst()
            ->limit(3)
            ->get();
    }

    public function updatedCategoryId(): void
    {
        // Jenis kendala bergantung pada kategori, jadi pilihan lama dibuang.
        $this->issueTypeId = '';
    }

    public function updatedPhotos(): void
    {
        // Beri umpan balik langsung saat foto dipilih.
        $this->validate($this->photoRules());
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);

        $this->photos = array_values($this->photos);
        $this->resetErrorBag();
    }

    /**
     * @return array<string, mixed>
     */
    protected function photoRules(): array
    {
        $maxFiles = max(1, (int) config('silapor.photos.max_files', 3));
        $maxKb = max(1, (int) config('silapor.photos.max_size_kb', 2048));

        return [
            'photos' => ['array', 'max:'.$maxFiles],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $isGuest = Auth::guest();

        return [
            'categoryId' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'issueTypeId' => [
                'nullable',
                Rule::exists('issue_types', 'id')
                    ->where('category_id', (int) $this->categoryId)
                    ->where('is_active', true),
            ],
            'reportTitle' => ['nullable', 'string', 'max:150'],
            'location' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'urgency' => ['required', Rule::enum(ReportUrgency::class)],
            'guestName' => [Rule::requiredIf($isGuest), 'nullable', 'string', 'max:100'],
            'guestContact' => [Rule::requiredIf($isGuest), 'nullable', 'string', 'max:100'],
            ...$this->photoRules(),
        ];
    }

    public function save(): void
    {
        if ($this->website !== '') {
            // Terdeteksi bot: pura-pura berhasil tanpa menyimpan apa pun.
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->validate();

        $user = Auth::user();
        $limitKey = 'guest-report:'.request()->ip();
        $limit = (int) config('silapor.guest_reports_per_hour', 5);

        if ($user === null && RateLimiter::tooManyAttempts($limitKey, $limit)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limitKey) / 60);

            $this->addError('rateLimit', "Terlalu banyak laporan dari perangkat ini. Coba lagi dalam {$minutes} menit.");

            return;
        }

        $report = app(CreateReport::class)(
            new NewReportData(
                categoryId: (int) $this->categoryId,
                description: $this->description,
                location: $this->location,
                issueTypeId: $this->issueTypeId !== '' ? (int) $this->issueTypeId : null,
                title: $this->reportTitle,
                urgency: ReportUrgency::from($this->urgency),
                guestName: $this->guestName,
                guestContact: $this->guestContact,
                photos: array_values($this->photos),
            ),
            $user,
        );

        if ($user === null) {
            RateLimiter::hit($limitKey, 3600);
        }

        session()->flash('report_created', $report->code);

        $this->redirect(route('reports.show', $report->code), navigate: true);
    }
};
?>

<div class="mx-auto max-w-2xl space-y-6">
    <div class="space-y-2">
        <flux:heading size="xl" level="1">Buat Laporan</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Ceritakan kendala yang Anda alami. Setelah terkirim, Anda akan mendapat kode laporan untuk memantau progresnya.
        </p>
    </div>

    @auth
        <div class="rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
            Melapor sebagai <span class="font-medium">{{ auth()->user()->name }}</span>
            @if (auth()->user()->unit)
                ({{ auth()->user()->unit }})
            @endif
        </div>
    @else
        <div class="rounded-xl border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
            Anda melapor sebagai tamu.
            <a href="{{ route('login') }}" class="font-medium underline">Masuk</a> atau
            <a href="{{ route('register') }}" class="font-medium underline">daftar</a>
            agar laporan Anda mudah dilacak.
        </div>
    @endauth

    <form wire:submit="save" class="space-y-5">
        <flux:select wire:model.live="categoryId" label="Kategori">
            <flux:select.option value="">Pilih kategori</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($this->issueTypes->isNotEmpty())
            <flux:select wire:model="issueTypeId" label="Jenis kendala (opsional)">
                <flux:select.option value="">Tidak tahu / lainnya</flux:select.option>
                @foreach ($this->issueTypes as $type)
                    <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:input
            wire:model.live.debounce.500ms="location"
            label="Lokasi"
            placeholder="Contoh: Ruang Rapat Lt. 2"
        />

        @if ($this->similarReports->isNotEmpty())
            <div class="space-y-2 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                <p class="font-medium">Sudah ada laporan aktif serupa di lokasi ini</p>
                <p>
                    Jika masalahnya sama, Anda tidak perlu membuat laporan baru. Pantau progresnya di laporan yang sudah ada.
                    Jika berbeda, silakan lanjutkan mengisi form.
                </p>

                <ul class="space-y-1">
                    @foreach ($this->similarReports as $similar)
                        <li wire:key="similar-{{ $similar->id }}">
                            <a href="{{ route('reports.show', $similar->code) }}" target="_blank" rel="noopener" class="underline">
                                <span class="font-mono font-semibold">{{ $similar->display_code }}</span>
                                {{ $similar->title }}
                            </a>
                            <span class="text-xs">({{ $similar->status->label() }})</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <flux:input
            wire:model="reportTitle"
            label="Judul singkat (opsional)"
            placeholder="Contoh: WiFi sering putus"
        />

        <flux:textarea
            wire:model="description"
            label="Deskripsi kendala"
            rows="5"
            placeholder="Jelaskan apa yang terjadi, sejak kapan, dan dampaknya."
        />

        <flux:select wire:model="urgency" label="Tingkat urgensi">
            @foreach (\App\Enums\ReportUrgency::cases() as $level)
                <flux:select.option value="{{ $level->value }}">{{ $level->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="space-y-2">
            <label for="photos" class="text-sm font-medium">Foto pendukung (opsional)</label>

            <input
                id="photos"
                type="file"
                wire:model="photos"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:hover:file:bg-zinc-600"
            >

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Maksimal {{ config('silapor.photos.max_files') }} foto, masing-masing paling besar
                {{ (int) (config('silapor.photos.max_size_kb') / 1024) }} MB (JPG, PNG, atau WebP).
            </p>

            <p wire:loading wire:target="photos" class="text-sm text-zinc-500 dark:text-zinc-400">Mengunggah foto…</p>

            @error('photos')
                <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror

            @foreach ($errors->get('photos.*') as $messages)
                @foreach ($messages as $message)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @endforeach
            @endforeach

            @if (count($photos) > 0)
                <div class="flex flex-wrap gap-3 pt-1">
                    @foreach ($photos as $index => $photo)
                        <div wire:key="photo-{{ $index }}" class="relative">
                            @if (method_exists($photo, 'isPreviewable') && $photo->isPreviewable())
                                <img
                                    src="{{ $photo->temporaryUrl() }}"
                                    alt="Pratinjau foto {{ $index + 1 }}"
                                    class="size-24 rounded-lg border border-zinc-200 object-cover dark:border-zinc-700"
                                >
                            @else
                                <div class="flex size-24 items-center justify-center rounded-lg border border-zinc-200 text-xs dark:border-zinc-700">
                                    Foto {{ $index + 1 }}
                                </div>
                            @endif

                            <button
                                type="button"
                                wire:click="removePhoto({{ $index }})"
                                class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
                                aria-label="Hapus foto {{ $index + 1 }}"
                            >
                                ×
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @guest
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="guestName" label="Nama Anda" autocomplete="name" />
                <flux:input
                    wire:model="guestContact"
                    label="Kontak (HP atau email)"
                    placeholder="Agar staf IT dapat menghubungi Anda"
                />
            </div>
        @endguest

        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="website">Jangan diisi</label>
            <input type="text" id="website" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        @error('rateLimit')
            <p class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>
        @enderror

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('reports.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Kirim Laporan</flux:button>
        </div>
    </form>
</div>
