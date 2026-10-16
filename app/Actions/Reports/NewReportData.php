<?php

namespace App\Actions\Reports;

use App\Enums\ReportUrgency;
use Illuminate\Http\UploadedFile;

/**
 * Data laporan baru yang sudah divalidasi oleh lapisan tampilan (form).
 */
class NewReportData
{
    /**
     * @param  array<int, UploadedFile>  $photos  Foto pendukung (opsional)
     */
    public function __construct(
        public readonly int $categoryId,
        public readonly string $description,
        public readonly string $location,
        public readonly ?int $issueTypeId = null,
        public readonly ?string $title = null,
        public readonly ReportUrgency $urgency = ReportUrgency::Medium,
        public readonly ?string $guestName = null,
        public readonly ?string $guestContact = null,
        public readonly array $photos = [],
    ) {}
}
