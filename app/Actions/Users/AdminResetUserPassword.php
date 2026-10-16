<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Mereset password pengguna oleh admin (ADM-05 / AUTH-06).
 *
 * Membuat password sementara acak yang dikembalikan sekali agar admin dapat
 * menyampaikannya ke pengguna. Password disimpan hanya dalam bentuk hash.
 * Sesi login lama pengguna itu diakhiri.
 */
class AdminResetUserPassword
{
    public function __invoke(User $actor, User $target): string
    {
        Gate::forUser($actor)->authorize('manage', $target);

        $password = Str::password(12, symbols: false);

        // Cast 'hashed' pada model User menyimpan password sebagai hash.
        $target->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $this->endSessions($target);

        return $password;
    }

    /**
     * Menghapus sesi login lama bila sesi disimpan di database.
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = config('session.connection');
        $table = config('session.table', 'sessions');

        DB::connection(is_string($connection) ? $connection : null)
            ->table(is_string($table) ? $table : 'sessions')
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
