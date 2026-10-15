<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController
{
    /**
     * Admin diarahkan ke dashboard admin; user biasa ke halaman Laporan Saya.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('reports.mine');
    }
}
