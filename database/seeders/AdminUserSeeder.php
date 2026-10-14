<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Membuat (atau memastikan) akun Staf IT pertama dari konfigurasi .env.
     * Aman dijalankan berulang kali: password akun yang sudah ada tidak diubah.
     */
    public function run(): void
    {
        $name = config('silapor.admin.name');
        $email = config('silapor.admin.email');
        $password = config('silapor.admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || strlen($password) < 8) {
            $this->command->warn('Akun admin dilewati: isi ADMIN_EMAIL dan ADMIN_PASSWORD (minimal 8 karakter) di file .env.');

            return;
        }

        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => is_string($name) && $name !== '' ? $name : 'Staf IT',
                'password' => $password,
                'unit' => 'IT',
                'email_verified_at' => now(),
            ]);
        }

        // 'role' dan 'is_active' tidak mass-assignable, jadi diatur secara eksplisit.
        $user->forceFill([
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();

        $this->command->info("Akun admin siap: {$email}");
    }
}
