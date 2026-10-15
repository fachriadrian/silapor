<?php

namespace App\Actions\Reports;

use App\Enums\LogEvent;
use App\Enums\ReportUrgency;
use App\Models\Report;
use App\Models\ReportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Mengubah tingkat urgensi laporan (hanya admin) dan mencatatnya di log.
 * Jika urgensinya sama, tidak ada yang berubah dan tidak ada log baru.
 */
class ChangeReportUrgency
{
    public function __invoke(Report $report, ReportUrgency $urgency, User $actor): Report
    {
        return DB::transaction(function () use ($report, $urgency, $actor): Report {
            $current = Report::query()->lockForUpdate()->findOrFail((int) $report->getKey());

            Gate::forUser($actor)->authorize('changeUrgency', $current);

            $from = $current->urgency;

            if ($from === $urgency) {
                return $current;
            }

            $current->urgency = $urgency;
            $current->save();

            ReportLog::record(
                $current,
                LogEvent::UrgencyChanged,
                $actor,
                "Urgensi diubah dari {$from->label()} menjadi {$urgency->label()}.",
            );

            return $current;
        });
    }
}
