<?php

namespace App\Actions\Reports;

use App\Models\Category;

/**
 * Menghasilkan kode laporan berikutnya untuk sebuah kategori, mis. "WF001".
 *
 * WAJIB dipanggil di dalam DB::transaction() bersama pembuatan laporan.
 * Baris kategori dikunci (lockForUpdate) agar dua laporan yang masuk
 * bersamaan tidak mendapat nomor yang sama. Nomor hanya naik dan tidak
 * pernah dipakai ulang. Indeks unik pada reports.code menjadi jaring
 * pengaman terakhir.
 */
class GenerateReportCode
{
    public function __invoke(int $categoryId): string
    {
        $category = Category::query()
            ->whereKey($categoryId)
            ->lockForUpdate()
            ->firstOrFail();

        $category->increment('last_seq');

        // Minimal 3 digit; otomatis melebar menjadi 4 digit setelah 999.
        return $category->prefix.str_pad((string) $category->last_seq, 3, '0', STR_PAD_LEFT);
    }
}
