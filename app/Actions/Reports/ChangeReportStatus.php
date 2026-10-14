<?php

namespace App\Actions\Reports;

use App\Enums\LogEvent;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah status laporan sesuai alur di PRD (bagian 7.1) dan mencatatnya
 * di log progres.
 *
 * - Pembatalan memakai kebijakan 'cancel' (admin atau pemilik laporan).
 * - Perubahan status lainnya memakai kebijakan 'changeStatus' (hanya admin).
 * - Laporan dikunci selama proses agar dua perubahan bersamaan tidak
 *   saling menimpa.
 */
class ChangeReportStatus
{
    public function __invoke(Report $report, ReportStatus $to, User $actor, ?string $note = null): Report
    {
        $note = $this->normalizeNote($note);

        return DB::transaction(function () use ($report, $to, $actor, $note): Report {
            $current = Report::query()->lockForUpdate()->findOrFail((int) $report->getKey());

            Gate::forUser($actor)->authorize(
                $to === ReportStatus::Cancelled ? 'cancel' : 'changeStatus',
                $current,
            );

            $from = $current->status;

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Status tidak dapat diubah dari {$from->label()} ke {$to->label()}.",
                ]);
            }

            if ($note === null && $this->requiresNote($from, $to)) {
                throw ValidationException::withMessages([
                    'note' => 'Keterangan wajib diisi untuk perubahan status ini.',
                ]);
            }

            $current->status = $to;

            if ($to === ReportStatus::Completed) {
                $current->completed_at = now();
            }

            if ($from === ReportStatus::Completed) {
                // Dibuka kembali: laporan tidak lagi dianggap selesai.
                $current->completed_at = null;
            }

            if ($to === ReportStatus::Cancelled) {
                $current->cancelled_at = now();
            }

            $current->save();

            $event = match (true) {
                $to === ReportStatus::Cancelled => LogEvent::Cancelled,
                $from === ReportStatus::Completed => LogEvent::Reopened,
                default => LogEvent::StatusChanged,
            };

            ReportLog::record($current, $event, $actor, $note, $from, $to);

            return $current;
        });
    }

    /**
     * Keterangan wajib saat ditunda, dibatalkan, atau dibuka kembali (LOG-06).
     */
    private function requiresNote(ReportStatus $from, ReportStatus $to): bool
    {
        return in_array($to, [ReportStatus::OnHold, ReportStatus::Cancelled], true)
            || $from === ReportStatus::Completed;
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = $note === null ? '' : trim($note);

        return $note === '' ? null : $note;
    }
}
