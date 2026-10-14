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

];
