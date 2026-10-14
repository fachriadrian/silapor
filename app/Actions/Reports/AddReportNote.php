<?php

namespace App\Actions\Reports;

use App\Enums\LogEvent;
use App\Models\Report;
use App\Models\ReportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menambahkan keterangan progres tanpa mengubah status (LOG-05).
 */
class AddReportNote
{
    public function __invoke(Report $report, User $actor, string $note): Report
    {
        Gate::forUser($actor)->authorize('addNote', $report);

        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'Keterangan progres wajib diisi.',
            ]);
        }

        return DB::transaction(function () use ($report, $actor, $note): Report {
            ReportLog::record($report, LogEvent::Note, $actor, $note);

            // Menandai laporan baru saja diperbarui.
            $report->touch();

            return $report;
        });
    }
}
