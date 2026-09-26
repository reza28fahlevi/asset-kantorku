<?php

namespace App\Support;

/** Pola pencarian LIKE/ILIKE yang aman: karakter khusus dari input user dicari apa adanya. */
final class Like
{
    /** Escape backslash (escape default PostgreSQL), % dan _. */
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }

    /** Pola "mengandung": %term%. */
    public static function contains(?string $term): string
    {
        return '%'.self::escape(trim((string) $term)).'%';
    }
}
