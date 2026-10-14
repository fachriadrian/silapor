<?php

namespace App\Actions\Reports;

use App\Enums\ReportUrgency;

/**
 * Data laporan baru yang sudah divalidasi oleh lapisan tampilan (form).
 */
class NewReportData
{
    public function __construct(
        public readonly int $categoryId,
        public readonly string $description,
        public readonly string $location,
        public readonly ?int $issueTypeId = null,
        public readonly ?string $title = null,
        public readonly ReportUrgency $urgency = ReportUrgency::Medium,
        public readonly ?string $guestName = null,
        public readonly ?string $guestContact = null,
    ) {}
}
