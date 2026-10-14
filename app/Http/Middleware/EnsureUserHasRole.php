<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses berdasarkan peran, contoh pemakaian: ->middleware('role:admin').
 * Jika nama peran tidak dikenal, akses ditolak (fail closed).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $required = UserRole::tryFrom($role);
        $user = $request->user();

        if ($required === null || ! $user instanceof User || $user->role !== $required) {
            abort(403);
        }

        return $next($request);
    }
}
