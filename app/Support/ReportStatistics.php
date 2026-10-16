<?php

namespace App\Support;

use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Perhitungan statistik laporan untuk halaman admin.
 *
 * Data dimuat sekali (hanya kolom yang dibutuhkan) lalu dikelompokkan di PHP.
 * Dengan volume sebuah kantor kecil ini cukup cepat, dan menghindari fungsi
 * tanggal SQL yang berbeda antar database.
 *
 * Laporan dibatalkan dikecualikan dari analisis, kecuali $includeCancelled.
 */
class ReportStatistics
{
    /** @var Collection<int, Report>|null */
    private ?Collection $loaded = null;

    public function __construct(
        private readonly StatisticsPeriod $period,
        private readonly bool $includeCancelled = false,
    ) {}

    /**
     * Semua laporan yang dibuat dalam periode (termasuk yang dibatalkan).
     *
     * @return Collection<int, Report>
     */
    private function reports(): Collection
    {
        if ($this->loaded === null) {
            $this->loaded = Report::query()
                ->whereBetween('created_at', [$this->period->start, $this->period->end])
                ->get(['id', 'category_id', 'issue_type_id', 'location', 'status', 'created_at', 'completed_at']);
        }

        return $this->loaded;
    }

    /**
     * Laporan yang ikut dianalisis.
     *
     * @return Collection<int, Report>
     */
    private function analysed(): Collection
    {
        if ($this->includeCancelled) {
            return $this->reports();
        }

        return $this->reports()
            ->reject(fn (Report $report) => $report->status === ReportStatus::Cancelled)
            ->values();
    }

    /**
     * @return array{total: int, active: int, completed: int, cancelled: int, average_resolution_hours: float|null}
     */
    public function summary(): array
    {
        $analysed = $this->analysed();

        $completed = $analysed->filter(
            fn (Report $report) => $report->status === ReportStatus::Completed && $report->completed_at !== null
        );

        $averageSeconds = $completed->isEmpty()
            ? null
            : $completed->avg(fn (Report $report) => $this->resolutionSeconds($report));

        return [
            'total' => $analysed->count(),
            'active' => $analysed->reject(fn (Report $report) => $report->status->isFinal())->count(),
            'completed' => $analysed->filter(fn (Report $report) => $report->status === ReportStatus::Completed)->count(),
            'cancelled' => $this->reports()->filter(fn (Report $report) => $report->status === ReportStatus::Cancelled)->count(),
            'average_resolution_hours' => $averageSeconds === null ? null : ((float) $averageSeconds) / 3600,
        ];
    }

    /**
     * Jumlah laporan per kategori (jenis kendala), terbanyak dulu.
     *
     * @return array<int, array{id: int, name: string, count: int, percent: int}>
     */
    public function byCategory(): array
    {
        $analysed = $this->analysed();
        $total = $analysed->count();
        $names = Category::query()->pluck('name', 'id');

        $rows = [];

        foreach ($analysed->groupBy('category_id') as $categoryId => $group) {
            $count = $group->count();

            $rows[] = [
                'id' => (int) $categoryId,
                'name' => (string) ($names->get($categoryId) ?? 'Tanpa kategori'),
                'count' => $count,
                'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * Rata-rata waktu penyelesaian per kategori, yang paling lama dulu.
     *
     * @return array<int, array{name: string, count: int, average_hours: float}>
     */
    public function resolutionByCategory(): array
    {
        $names = Category::query()->pluck('name', 'id');

        $completed = $this->analysed()->filter(
            fn (Report $report) => $report->status === ReportStatus::Completed && $report->completed_at !== null
        );

        $rows = [];

        foreach ($completed->groupBy('category_id') as $categoryId => $group) {
            $rows[] = [
                'name' => (string) ($names->get($categoryId) ?? 'Tanpa kategori'),
                'count' => $group->count(),
                'average_hours' => ((float) $group->avg(fn (Report $report) => $this->resolutionSeconds($report))) / 3600,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['average_hours'] <=> $a['average_hours']);

        return $rows;
    }

    /**
     * Kombinasi kategori + jenis kendala + lokasi yang muncul berkali-kali.
     * Kombinasi yang muncul minimal $threshold kali dalam rentang $windowDays
     * hari ditandai 'recurring'. Kombinasi yang hanya muncul sekali tidak
     * ditampilkan. Lokasi dinormalisasi (huruf kecil, spasi dirapikan).
     *
     * @return array<int, array{category_id: int, category: string, issue_type: string|null, location: string, count: int, last_at: Carbon, recurring: bool}>
     */
    public function recurring(int $threshold, int $windowDays): array
    {
        $threshold = max(2, $threshold);
        $categories = Category::query()->pluck('name', 'id');
        $issueTypes = IssueType::query()->pluck('name', 'id');

        $groups = $this->analysed()->groupBy(
            fn (Report $report) => $report->category_id.'|'.($report->issue_type_id ?? 0).'|'.$this->normalizeLocation($report->location)
        );

        $rows = [];

        foreach ($groups as $group) {
            if ($group->count() < 2) {
                continue;
            }

            /** @var Report $first */
            $first = $group->sortBy('created_at')->first();
            $last = $group->sortByDesc('created_at')->first();

            $displayLocation = $group
                ->countBy(fn (Report $report) => Str::squish($report->location))
                ->sortDesc()
                ->keys()
                ->first();

            $rows[] = [
                'category_id' => $first->category_id,
                'category' => (string) ($categories->get($first->category_id) ?? 'Tanpa kategori'),
                'issue_type' => $first->issue_type_id !== null ? $issueTypes->get($first->issue_type_id) : null,
                'location' => (string) $displayLocation,
                'count' => $group->count(),
                'last_at' => $last->created_at,
                'recurring' => $this->hasBurst($group, $threshold, $windowDays),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['location'], $b['location']));

        return $rows;
    }

    /**
     * Tren jumlah laporan per kategori selama beberapa bulan terakhir
     * (tidak bergantung pada periode yang dipilih).
     *
     * @return array{months: array<int, string>, rows: array<int, array{id: int, name: string, counts: array<int, int>, total: int}>, max: int}
     */
    public function monthlyTrend(int $months = 6): array
    {
        $timezone = (string) config('silapor.display_timezone', 'Asia/Jakarta');
        $start = CarbonImmutable::now($timezone)->startOfMonth()->subMonths($months - 1);

        $labels = [];
        $keys = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $keys[] = $month->format('Y-m');
            $labels[] = $month->translatedFormat('M Y');
        }

        $query = Report::query()->where('created_at', '>=', $start->setTimezone('UTC'));

        if (! $this->includeCancelled) {
            $query->where('status', '!=', ReportStatus::Cancelled->value);
        }

        $reports = $query->get(['category_id', 'created_at']);
        $names = Category::query()->pluck('name', 'id');

        /** @var array<int, array<string, int>> $grid */
        $grid = [];

        foreach ($reports as $report) {
            $monthKey = $report->created_at->copy()->setTimezone($timezone)->format('Y-m');

            if (! in_array($monthKey, $keys, true)) {
                continue;
            }

            $grid[$report->category_id][$monthKey] = ($grid[$report->category_id][$monthKey] ?? 0) + 1;
        }

        $rows = [];
        $max = 0;

        foreach ($grid as $categoryId => $perMonth) {
            $counts = array_map(fn (string $key) => $perMonth[$key] ?? 0, $keys);
            $max = max($max, ...$counts);

            $rows[] = [
                'id' => $categoryId,
                'name' => (string) ($names->get($categoryId) ?? 'Tanpa kategori'),
                'counts' => $counts,
                'total' => array_sum($counts),
            ];
        }

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']));

        return ['months' => $labels, 'rows' => $rows, 'max' => $max];
    }

    private function resolutionSeconds(Report $report): int
    {
        if ($report->completed_at === null) {
            return 0;
        }

        return $report->completed_at->getTimestamp() - $report->created_at->getTimestamp();
    }

    private function normalizeLocation(string $location): string
    {
        return Str::of($location)->lower()->squish()->toString();
    }

    /**
     * Apakah ada $threshold laporan berurutan yang rentang waktunya
     * tidak lebih dari $windowDays hari?
     *
     * @param  Collection<int, Report>  $group
     */
    private function hasBurst(Collection $group, int $threshold, int $windowDays): bool
    {
        $times = $group
            ->map(fn (Report $report) => $report->created_at->getTimestamp())
            ->sort()
            ->values()
            ->all();

        $window = $windowDays * 86400;

        for ($i = 0; $i + $threshold - 1 < count($times); $i++) {
            if ($times[$i + $threshold - 1] - $times[$i] <= $window) {
                return true;
            }
        }

        return false;
    }
}
