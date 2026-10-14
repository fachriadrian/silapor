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

];
