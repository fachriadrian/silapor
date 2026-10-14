<?php

use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Enums\ReportUrgency;
use App\Models\Category;
use App\Models\IssueType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public'), Title('Buat Laporan')] class extends Component {
    public string $categoryId = '';

    public string $issueTypeId = '';

    public string $location = '';

    public string $reportTitle = '';

    public string $description = '';

    public string $urgency = 'sedang';

    public string $guestName = '';

    public string $guestContact = '';

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

    public function updatedCategoryId(): void
    {
        // Jenis kendala bergantung pada kategori, jadi pilihan lama dibuang.
        $this->issueTypeId = '';
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
            wire:model="location"
            label="Lokasi"
            placeholder="Contoh: Ruang Rapat Lt. 2"
        />

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
