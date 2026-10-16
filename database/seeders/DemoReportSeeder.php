<?php

namespace Database\Seeders;

use App\Actions\Reports\ChangeReportStatus;
use App\Actions\Reports\CreateReport;
use App\Actions\Reports\NewReportData;
use App\Enums\ReportStatus;
use App\Enums\ReportUrgency;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk mencoba dashboard dan statistik. TIDAK dipanggil oleh
 * DatabaseSeeder; jalankan manual:
 *
 *     php artisan db:seed --class=DemoReportSeeder
 *
 * Perlu CategorySeeder dan akun admin (php artisan db:seed) lebih dulu.
 */
class DemoReportSeeder extends Seeder
{
    /**
     * Contoh laporan: [prefix kategori, lokasi, judul, jumlah].
     * Sebagian sengaja berulang di lokasi yang sama agar analisis kendala
     * berulang punya bahan.
     */
    private const SAMPLES = [
        ['WF', 'Ruang Rapat Lt. 2', 'WiFi putus-putus', 6],
        ['WF', 'Ruang Keuangan', 'WiFi lemot', 2],
        ['KOM', 'Ruang Keuangan', 'Komputer tidak mau menyala', 3],
        ['PRN', 'Lobi', 'Printer macet', 4],
        ['SW', 'Ruang HRD', 'Aplikasi error saat dibuka', 2],
        ['AKN', 'Ruang Marketing', 'Lupa password email', 2],
        ['PRS', 'Ruang Rapat Lt. 1', 'Proyektor tidak menampilkan gambar', 2],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DemoReportSeeder tidak boleh dijalankan di produksi.');

            return;
        }

        $admin = User::query()->where('role', UserRole::Admin->value)->first();

        if ($admin === null) {
            $this->command->warn('Akun admin belum ada. Jalankan php artisan db:seed terlebih dulu.');

            return;
        }

        $categories = Category::query()->get()->keyBy('prefix');
        $create = app(CreateReport::class);
        $change = app(ChangeReportStatus::class);
        $total = 0;

        foreach (self::SAMPLES as [$prefix, $location, $title, $count]) {
            $category = $categories->get($prefix);

            if ($category === null) {
                continue;
            }

            for ($i = 0; $i < $count; $i++) {
                $report = $create(new NewReportData(
                    categoryId: $category->id,
                    description: $title.' di '.$location.'. '.fake()->sentence(),
                    location: $location,
                    title: $title,
                    urgency: fake()->randomElement(ReportUrgency::cases()),
                    guestName: fake()->name(),
                    guestContact: fake()->phoneNumber(),
                ));

                $outcome = fake()->randomElement([
                    'baru', 'baru', 'diproses', 'ditunda', 'selesai', 'selesai', 'selesai', 'dibatalkan',
                ]);

                match ($outcome) {
                    'diproses' => $change($report, ReportStatus::InProgress, $admin, 'Sedang ditangani.'),
                    'ditunda' => $change(
                        $change($report, ReportStatus::InProgress, $admin, 'Sedang ditangani.'),
                        ReportStatus::OnHold,
                        $admin,
                        'Menunggu sparepart.',
                    ),
                    'selesai' => $change($report, ReportStatus::Completed, $admin, 'Sudah teratasi.'),
                    'dibatalkan' => $change($report, ReportStatus::Cancelled, $admin, 'Laporan duplikat.'),
                    default => $report,
                };

                // Mundurkan tanggalnya agar tersebar dalam ~2 bulan terakhir.
                $createdAt = now()->subDays(random_int(0, 55))->subMinutes(random_int(0, 600));
                $finishedAt = $createdAt->copy()->addHours(random_int(2, 72));

                if ($finishedAt->isFuture()) {
                    $finishedAt = now();
                }

                Report::query()->whereKey($report->id)->update([
                    'created_at' => $createdAt,
                    'updated_at' => in_array($outcome, ['selesai', 'dibatalkan'], true) ? $finishedAt : $createdAt,
                    'completed_at' => $outcome === 'selesai' ? $finishedAt : null,
                    'cancelled_at' => $outcome === 'dibatalkan' ? $finishedAt : null,
                ]);

                $total++;
            }
        }

        $this->command->info("{$total} laporan contoh dibuat.");
    }
}
