<?php

namespace App\Libraries;

/** Tingkatan kelas (X, XI, XII) dibaca dari awal nama kelas, mis. "XI-3" → XI. */
class Tingkat
{
    public const LIST = ['X', 'XI', 'XII'];

    public static function of(?string $kelas): string
    {
        return preg_match('/^\s*(XII|XI|X)(?![A-Z])/i', (string) $kelas, $m) ? strtoupper($m[1]) : '';
    }

    /** Apakah kelas termasuk tingkatan itu ('' = semua tingkatan). */
    public static function cocok(?string $kelas, string $tingkat): bool
    {
        return $tingkat === '' || self::of($kelas) === $tingkat;
    }

    /** Nilai tingkatan dari request GET, divalidasi. */
    public static function dariRequest(): string
    {
        $t = strtoupper(trim((string) service('request')->getGet('tingkat')));

        return in_array($t, self::LIST, true) ? $t : '';
    }

    /** Pola SQL REGEXP untuk kolom kelas, mis. '^XI([^A-Z]|$)'. */
    public static function regexp(string $tingkat): string
    {
        return '^' . $tingkat . '([^A-Za-z]|$)';
    }
}
