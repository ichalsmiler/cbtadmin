<?php

namespace App\Libraries;

/** Identitas sekolah untuk kop dan tanda tangan dokumen. */
class School
{
    public const UPLOAD_DIR = WRITEPATH . 'uploads/';

    private const HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function info(): array
    {
        $path = self::logoPath();

        return [
            'nama_sekolah'   => Settings::get('nama_sekolah', (string) env('cbt.namaSekolah', '')),
            'npsn'           => Settings::get('npsn', (string) env('cbt.npsn', '')),
            'alamat'         => Settings::get('alamat'),
            'kota'           => Settings::get('kota'),
            'kepala_sekolah' => Settings::get('kepala_sekolah'),
            'nip_kepala'     => Settings::get('nip_kepala'),
            'nama_kegiatan'  => Settings::get('nama_kegiatan'),
            'logo'           => $path ? 'data:' . mime_content_type($path) . ';base64,' . base64_encode((string) file_get_contents($path)) : null,
        ];
    }

    public static function logoPath(): ?string
    {
        $name = basename(Settings::get('logo_file'));
        $path = self::UPLOAD_DIR . $name;

        return $name !== '' && is_file($path) ? $path : null;
    }

    public static function removeLogo(): void
    {
        foreach (glob(self::UPLOAD_DIR . 'logo.*') ?: [] as $file) {
            @unlink($file);
        }
        Settings::set('logo_file', '');
    }

    /** "Jumat, 13 November 2026" */
    public static function tanggal(int $ts, bool $withDay = true): string
    {
        $text = (int) date('j', $ts) . ' ' . self::BULAN[(int) date('n', $ts)] . ' ' . date('Y', $ts);

        return $withDay ? self::HARI[(int) date('w', $ts)] . ', ' . $text : $text;
    }
}
