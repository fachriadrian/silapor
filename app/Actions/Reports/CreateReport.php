<?php

namespace App\Actions\Reports;

use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Models\Category;
use App\Models\IssueType;
use App\Models\Report;
use App\Models\ReportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Membuat laporan baru, baik dari user terdaftar maupun guest.
 * Kode laporan dan entri log "dibuat" dibuat dalam satu transaksi.
 */
class CreateReport
{
    public function __invoke(NewReportData $data, ?User $reporter = null): Report
    {
        $guestName = trim((string) $data->guestName);
        $guestContact = trim((string) $data->guestContact);

        if ($reporter === null && ($guestName === '' || $guestContact === '')) {
            throw new InvalidArgumentException('Laporan dari tamu wajib menyertakan nama dan kontak.');
        }

        if (count($data->photos) > (int) config('silapor.photos.max_files', 3)) {
            throw new InvalidArgumentException('Jumlah foto melebihi batas yang diizinkan.');
        }

        $category = Category::query()->active()->findOrFail($data->categoryId);

        if ($data->issueTypeId !== null) {
            IssueType::query()
                ->active()
                ->whereKey($data->issueTypeId)
                ->where('category_id', $category->id)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($data, $reporter, $category, $guestName, $guestContact): Report {
            $report = new Report([
                'category_id' => $category->id,
                'issue_type_id' => $data->issueTypeId,
                'title' => $this->titleFor($data, $category),
                'description' => trim($data->description),
                'location' => trim($data->location),
                'urgency' => $data->urgency,
                'reporter_user_id' => $reporter?->getKey(),
                'guest_name' => $reporter === null ? $guestName : null,
                'guest_contact' => $reporter === null ? $guestContact : null,
            ]);

            // 'code' tidak mass-assignable, jadi diisi eksplisit di sini.
            $report->code = app(GenerateReportCode::class)($category->id);
            $report->save();

            $this->storePhotos($report, $data);

            ReportLog::record(
                $report,
                LogEvent::Created,
                $reporter,
                to: ReportStatus::New,
                guestName: $guestName,
            );

            return $report;
        });
    }

    /**
     * Menyimpan foto pendukung ke disk 'public' dan mencatatnya di tabel
     * attachments. Nama file dibuat acak oleh Laravel, bukan dari nama asli.
     */
    private function storePhotos(Report $report, NewReportData $data): void
    {
        foreach ($data->photos as $photo) {
            $path = $photo->store("reports/{$report->id}", 'public');

            if ($path === false) {
                throw new RuntimeException('Foto gagal disimpan.');
            }

            $report->attachments()->create([
                'path' => $path,
                'mime_type' => (string) $photo->getMimeType(),
                'size_bytes' => (int) $photo->getSize(),
            ]);
        }
    }

    /**
     * Judul kosong diganti otomatis dari nama kategori (RPT-02).
     */
    private function titleFor(NewReportData $data, Category $category): string
    {
        $title = trim((string) $data->title);

        return $title !== '' ? $title : 'Kendala '.$category->name;
    }
}
