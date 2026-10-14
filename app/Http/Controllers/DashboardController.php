<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController
{
    /**
     * Admin diarahkan ke dashboard admin; user biasa melihat dashboard user.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard');
    }
}
