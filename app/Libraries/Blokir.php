<?php

namespace App\Libraries;

/**
 * Blokir siswa karena pelanggaran (tabel `blokir`). Akun Moodle disuspend dan sesinya langsung
 * diputus; saat mencoba login, plugin Moodle local_cbtblokir menampilkan "User Anda terblokir".
 * Blokir ini berlaku sampai dibuka manual, dan tidak ikut dibuka oleh absensi.
 */
class Blokir
{
    /** Peserta (opsional satu ruang) beserta blokir aktifnya. */
    public static function daftar(?int $ruangId): array
    {
        $builder = db_connect()->table('peserta p')
            ->select('p.id, p.nomor_peserta, p.nama, p.kelas, p.username, p.ruang_id, r.nama AS ruang_nama, b.alasan, b.diblokir_oleh, b.diblokir_at')
            ->join('ruang r', 'r.id = p.ruang_id', 'left')
            ->join('blokir b', 'b.peserta_id = p.id AND b.aktif = 1', 'left')
            ->orderBy('LENGTH(r.nama)', '', false)->orderBy('r.nama')->orderBy('p.nomor_peserta')->orderBy('p.nama');
        if ($ruangId !== null) {
            $builder->where('p.ruang_id', $ruangId);
        }

        return $builder->get()->getResultArray();
    }

    /** ID peserta yang sedang diblokir karena pelanggaran. */
    public static function aktifIds(array $pesertaIds): array
    {
        if ($pesertaIds === []) {
            return [];
        }

        return array_map('intval', array_column(
            db_connect()->table('blokir')->select('peserta_id')->where('aktif', 1)->whereIn('peserta_id', $pesertaIds)->get()->getResultArray(),
            'peserta_id'
        ));
    }

    /** Blokir satu siswa: suspend + putus sesi Moodle, lalu catat. */
    public static function blokir(int $pesertaId, string $alasan, string $oleh): void
    {
        $p = self::peserta($pesertaId);
        if (self::aktifIds([$pesertaId]) !== []) {
            throw new \RuntimeException($p['nama'] . ' sudah diblokir.');
        }
        self::suspend($p['username'], true);
        db_connect()->table('blokir')->insert([
            'peserta_id'    => $pesertaId,
            'ruang_id'      => $p['ruang_id'],
            'alasan'        => mb_substr($alasan, 0, 200),
            'aktif'         => 1,
            'diblokir_oleh' => $oleh,
            'diblokir_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /** Buka blokir pelanggaran. Bila hari ini siswa juga diblokir absensi (tidak hadir), akun tetap disuspend. */
    public static function buka(int $pesertaId, string $oleh): bool
    {
        $p   = self::peserta($pesertaId);
        $db  = db_connect();
        $absen = $db->table('absensi')->where('peserta_id', $pesertaId)->where('tanggal', date('Y-m-d'))->where('diblokir', 1)->countAllResults() > 0;
        if (! $absen) {
            self::suspend($p['username'], false);
        }
        $db->table('blokir')->where('peserta_id', $pesertaId)->where('aktif', 1)
            ->update(['aktif' => 0, 'dibuka_oleh' => $oleh, 'dibuka_at' => date('Y-m-d H:i:s')]);

        return ! $absen;
    }

    private static function peserta(int $pesertaId): array
    {
        $p = db_connect()->table('peserta')->select('id, nama, username, ruang_id')->where('id', $pesertaId)->get()->getRowArray();
        if (! $p) {
            throw new \RuntimeException('Siswa tidak ditemukan.');
        }

        return $p;
    }

    private static function suspend(string $username, bool $suspend): void
    {
        $id = Moodle::db()->table(Moodle::t('user'))->select('id')->where('deleted', 0)->where('username', $username)->get()->getRowArray();
        if (! $id) {
            throw new \RuntimeException($username . ' belum ada di Moodle.');
        }
        $item = MoodleBridge::call('suspend_users', ['userids' => [(int) $id['id']], 'suspend' => $suspend])[0] ?? [];
        if (isset($item['error'])) {
            throw new \RuntimeException($item['error']);
        }
    }
}
