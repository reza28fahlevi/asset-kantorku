<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen berurutan & aman-konkurensi (row lock pada number_sequences).
 * Harus dipanggil di dalam transaksi database.
 */
class NumberGenerator
{
    public function next(string $key): int
    {
        DB::statement(
            'INSERT INTO number_sequences (key, last_value, updated_at) VALUES (?, 0, NOW()) ON CONFLICT (key) DO NOTHING',
            [$key]
        );

        $row = DB::selectOne('SELECT last_value FROM number_sequences WHERE key = ? FOR UPDATE', [$key]);
        $value = (int) $row->last_value + 1;

        DB::update('UPDATE number_sequences SET last_value = ?, updated_at = NOW() WHERE key = ?', [$value, $key]);

        return $value;
    }

    /** Contoh: PR-2026-00001 */
    public function requestNumber(string $prefix): string
    {
        $year = now()->format('Y');

        return sprintf('%s-%s-%05d', $prefix, $year, $this->next("{$prefix}-{$year}"));
    }

    /** Contoh: LPT-2026-00001 */
    public function assetTag(string $categoryCode): string
    {
        $year = now()->format('Y');
        $code = strtoupper($categoryCode);

        return sprintf('%s-%s-%05d', $code, $year, $this->next("ASSET-{$code}-{$year}"));
    }
}
