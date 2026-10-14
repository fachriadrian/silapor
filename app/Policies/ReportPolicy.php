<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Semua orang, termasuk guest (parameter user boleh null), dapat
     * melihat daftar dan detail laporan.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Report $report): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return true;
    }

    /**
     * Admin atau pemilik laporan dapat membatalkan, selama laporan belum
     * berstatus akhir (Selesai/Dibatalkan).
     */
    public function cancel(User $user, Report $report): bool
    {
        return $report->isCancellable()
            && ($user->isAdmin() || $report->isReportedBy($user));
    }

    public function changeStatus(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }

    public function addNote(User $user, Report $report): bool
    {
        return $user->isAdmin();
    }
}
