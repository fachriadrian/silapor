<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Kategori awal (prefix => nama). Admin dapat mengubah atau menambah
     * kategori lewat halaman admin.
     */
    private const CATEGORIES = [
        'WF' => 'Jaringan WiFi',
        'KOM' => 'Komputer / Laptop',
        'PRN' => 'Printer / Scanner',
        'NET' => 'Jaringan Kabel / Internet',
        'SW' => 'Software / Aplikasi',
        'AKN' => 'Akun & Akses',
        'PRS' => 'Perangkat Presentasi (proyektor/TV)',
        'LNN' => 'Lainnya',
    ];

    /**
     * Aman dijalankan berulang kali: kategori yang sudah ada tidak diubah,
     * sehingga perubahan nama oleh admin tidak tertimpa.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $prefix => $name) {
            Category::firstOrCreate(
                ['prefix' => $prefix],
                ['name' => $name],
            );
        }
    }
}
