<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

/**
 * Membatalkan laporan dengan alasan wajib (CNL-01 s.d. CNL-05).
 * Laporan tidak dihapus: statusnya menjadi Dibatalkan dan tercatat di log.
 */
class CancelReport
{
    public function __invoke(Report $report, User $actor, string $reason): Report
    {
        return app(ChangeReportStatus::class)($report, ReportStatus::Cancelled, $actor, $reason);
    }
}
