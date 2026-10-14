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
     * Judul kosong diganti otomatis dari nama kategori (RPT-02).
     */
    private function titleFor(NewReportData $data, Category $category): string
    {
        $title = trim((string) $data->title);

        return $title !== '' ? $title : 'Kendala '.$category->name;
    }
}
