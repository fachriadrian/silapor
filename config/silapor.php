<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Admin Awal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh AdminUserSeeder untuk membuat akun Staf IT pertama.
    | Isi lewat file .env, jangan menulis password langsung di kode.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Staf IT'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zona Waktu Tampilan
    |--------------------------------------------------------------------------
    |
    | Data disimpan dalam UTC; waktu ditampilkan ke pengguna dalam zona ini.
    |
    */

    'display_timezone' => 'Asia/Jakarta',

    /*
    |--------------------------------------------------------------------------
    | Batas Laporan Guest
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimum laporan dari satu alamat IP (tanpa login) per jam,
    | untuk mencegah spam.
    |
    */

    'guest_reports_per_hour' => (int) env('GUEST_REPORTS_PER_HOUR', 5),

    /*
    |--------------------------------------------------------------------------
    | Foto Laporan
    |--------------------------------------------------------------------------
    |
    | Batas unggahan foto pendukung pada form lapor (JPG, PNG, atau WebP).
    |
    */

    'photos' => [
        'max_files' => 3,
        'max_size_kb' => 2048,
    ],

    /*
    |--------------------------------------------------------------------------
    | Laporan Basi
    |--------------------------------------------------------------------------
    |
    | Laporan aktif yang belum diperbarui selama sekian hari ditandai di
    | dashboard admin agar tidak terlewat.
    |
    */

    'stale_days' => (int) env('STALE_REPORT_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Kendala Berulang
    |--------------------------------------------------------------------------
    |
    | Suatu kombinasi kategori + jenis kendala + lokasi dianggap "berulang"
    | bila muncul minimal 'recurring_threshold' kali dalam rentang
    | 'recurring_window_days' hari. Ambang dapat diubah langsung di halaman
    | statistik.
    |
    */

    'recurring_threshold' => (int) env('RECURRING_THRESHOLD', 3),

    'recurring_window_days' => (int) env('RECURRING_WINDOW_DAYS', 30),

];
