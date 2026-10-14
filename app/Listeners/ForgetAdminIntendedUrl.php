<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Jika sebelum login seseorang sempat membuka area /admin (sehingga URL itu
 * tersimpan sebagai "tujuan"), user non-admin tidak boleh diarahkan ke sana
 * setelah login atau registrasi karena hasilnya pasti 403.
 */
class ForgetAdminIntendedUrl
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User && $user->isAdmin()) {
            return;
        }

        $intended = session('url.intended');

        if (! is_string($intended)) {
            return;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (is_string($path) && ($path === '/admin' || str_starts_with($path, '/admin/'))) {
            session()->forget('url.intended');
        }
    }
}
