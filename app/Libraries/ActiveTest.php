<?php

namespace App\Libraries;

/**
 * Tes yang sedang aktif: satu kategori kursus Moodle, dan satu token bersama
 * yang dipasang sebagai password di semua quiz kategori itu.
 *
 * Disimpan di tabel `settings`:
 *   tes_kategori  id kategori Moodle (0 = belum dipilih)
 *   token_aktif   1 = pakai token dan release otomatis, 0 = tanpa token
 *   token_menit   jarak release otomatis (menit)
 *   token_nilai   token yang sedang berlaku
 *   token_waktu   waktu token terakhir di-release (unix time)
 */
class ActiveTest
{
    /** Huruf tanpa I dan O supaya tidak tertukar dengan 1 dan 0. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const TOKEN_LENGTH = 6;

    public static function categoryId(): int
    {
        return (int) Settings::get('tes_kategori', '0');
    }

    public static function tokenEnabled(): bool
    {
        return Settings::get('token_aktif', '1') === '1';
    }

    public static function tokenMinutes(): int
    {
        return max(1, (int) Settings::get('token_menit', '15'));
    }

    public static function token(): string
    {
        return self::tokenEnabled() ? Settings::get('token_nilai') : '';
    }

    public static function releasedAt(): int
    {
        return (int) Settings::get('token_waktu', '0');
    }

    /** Waktu release otomatis berikutnya, 0 bila token tidak aktif atau belum ada kategori. */
    public static function nextReleaseAt(): int
    {
        if (! self::tokenEnabled() || self::categoryId() === 0 || self::token() === '') {
            return 0;
        }

        return self::releasedAt() + self::tokenMinutes() * 60;
    }

    /** Sudah waktunya release otomatis? */
    public static function due(): bool
    {
        if (! self::tokenEnabled() || self::categoryId() === 0) {
            return false;
        }

        return self::token() === '' || time() >= self::nextReleaseAt();
    }

    /** Nama mapel yang dibuka (kursus tampil di kategori aktif), dipisah koma; '' bila belum ada tes. */
    public static function mapelAktif(): string
    {
        if (self::categoryId() === 0) {
            return '';
        }
        $kursus = array_filter(Moodle::coursesInCategory(self::categoryId()), static fn ($c) => (int) $c['visible'] === 1);

        return implode(', ', array_column($kursus, 'fullname'));
    }

    /**
     * Token disembunyikan dari pengawas ruang ini sampai absensi ruangnya hari ini disimpan
     * (minimal satu siswa, karena absensi bisa per tingkatan). Admin ($ruangId null) tidak dikunci.
     */
    public static function terkunciUntuk(?int $ruangId): bool
    {
        if ($ruangId === null || ! self::tokenEnabled()) {
            return false;
        }

        return db_connect()->table('absensi')->where('ruang_id', $ruangId)->where('tanggal', date('Y-m-d'))->countAllResults() === 0;
    }

    public static function newToken(): string
    {
        $token = '';
        for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
            $token .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $token;
    }

    /** Buat token baru dan pasang di semua quiz kategori aktif. */
    public static function release(): string
    {
        $token = self::newToken();
        self::apply($token);
        Settings::set('token_nilai', $token);
        Settings::set('token_waktu', (string) time());

        return $token;
    }

    /**
     * Pasang password di quiz kategori aktif yang password-nya belum sama.
     *
     * @return int jumlah quiz yang diubah
     */
    public static function apply(string $password, ?int $categoryId = null): int
    {
        $categoryId ??= self::categoryId();
        if ($categoryId === 0) {
            return 0;
        }

        $ids = [];
        foreach (Moodle::quizzes($categoryId) as $quiz) {
            if ((string) $quiz['password'] !== $password) {
                $ids[] = (int) $quiz['id'];
            }
        }
        if ($ids === []) {
            return 0;
        }

        $failed = array_filter(
            MoodleBridge::call('quiz_password', ['quizids' => $ids, 'password' => $password]),
            static fn ($item) => isset($item['error'])
        );
        if ($failed !== []) {
            throw new \RuntimeException('Token gagal dipasang di ' . count($failed) . ' quiz: ' . reset($failed)['error']);
        }

        return count($ids);
    }

    /**
     * Pastikan semua quiz kategori aktif memakai token yang berlaku,
     * termasuk quiz yang baru dibuat setelah token di-release.
     */
    public static function sync(): int
    {
        $token = self::token();

        return self::tokenEnabled() && $token !== '' ? self::apply($token) : 0;
    }
}
