<?php

namespace Database\Seeders;

/**
 * Membaca database/sql/asset_kantorku.sql sebagai satu-satunya sumber skema & data awal.
 * Bagian skema dan seed dipisahkan penanda @@SEED-START@@ / @@SEED-END@@.
 */
class SqlScript
{
    private const START = '-- @@SEED-START@@';
    private const END = '-- @@SEED-END@@';

    public static function schema(): string
    {
        $sql = self::contents();
        $body = substr($sql, 0, strpos($sql, self::START));

        // BEGIN/COMMIT di file dihapus: migration Laravel sudah berjalan dalam transaksi
        return preg_replace('/^BEGIN;\s*$/m', '', $body, 1);
    }

    public static function seed(): string
    {
        $sql = self::contents();
        $start = strpos($sql, "\n", strpos($sql, self::START)) + 1;

        return substr($sql, $start, strpos($sql, self::END) - $start);
    }

    private static function contents(): string
    {
        return file_get_contents(database_path('sql/asset_kantorku.sql'));
    }
}
