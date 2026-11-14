<?php

namespace App\Libraries;

/**
 * Absensi harian oleh pengawas ruang (tabel `absensi`).
 * Siswa yang ditandai tidak hadir diblokir (suspend) di Moodle hari itu dan
 * dibuka lagi bila ditandai hadir, oleh admin, atau otomatis setelah hari berganti.
 */
class Absensi
{
    public const HADIR       = 'hadir';
    public const TIDAK_HADIR = 'tidak_hadir';

    /** Siswa di ruang beserta status absensinya pada tanggal itu (null = belum diabsen). */
    public static function forRuang(int $ruangId, string $tanggal): array
    {
        return db_connect()->table('peserta p')
            ->select('p.id, p.nomor_peserta, p.nama, p.kelas, p.username, a.status, a.diblokir, a.dicatat_oleh, a.updated_at')
            ->join('absensi a', 'a.peserta_id = p.id AND a.tanggal = ' . db_connect()->escape($tanggal), 'left', false)
            ->where('p.ruang_id', $ruangId)
            ->orderBy('p.nomor_peserta')->orderBy('p.nama')
            ->get()->getResultArray();
    }

    /**
     * Simpan absensi beberapa siswa satu ruang, lalu blokir/buka blokir di Moodle.
     *
     * @param array<int, string> $statuses peserta_id => hadir|tidak_hadir
     * @return array{hadir:int, tidak:int, diblokir:int, dibuka:int, gagal:list<string>}
     */
    public static function save(int $ruangId, string $tanggal, array $statuses, string $oleh): array
    {
        $db     = db_connect();
        $now    = date('Y-m-d H:i:s');
        $result = ['hadir' => 0, 'tidak' => 0, 'diblokir' => 0, 'dibuka' => 0, 'gagal' => []];

        $peserta = array_column($db->table('peserta')->select('id, username')->where('ruang_id', $ruangId)->get()->getResultArray(), 'username', 'id');
        $lama    = array_column($db->table('absensi')->where('tanggal', $tanggal)->whereIn('peserta_id', array_keys($peserta) ?: [0])->get()->getResultArray(), null, 'peserta_id');

        $blokir = $buka = [];
        foreach ($statuses as $pid => $status) {
            $pid = (int) $pid;
            if (! isset($peserta[$pid]) || ! in_array($status, [self::HADIR, self::TIDAK_HADIR], true)) {
                continue;  // bukan siswa ruang ini
            }
            $row = ['status' => $status, 'ruang_id' => $ruangId, 'dicatat_oleh' => $oleh, 'updated_at' => $now];
            if (isset($lama[$pid])) {
                $db->table('absensi')->where('id', $lama[$pid]['id'])->update($row);
            } else {
                $db->table('absensi')->insert($row + ['tanggal' => $tanggal, 'peserta_id' => $pid, 'diblokir' => 0]);
            }
            $sedangDiblokir = (int) ($lama[$pid]['diblokir'] ?? 0) === 1;
            if ($status === self::TIDAK_HADIR) {
                $result['tidak']++;
                if (! $sedangDiblokir) {
                    $blokir[$pid] = $peserta[$pid];
                }
            } else {
                $result['hadir']++;
                if ($sedangDiblokir) {
                    $buka[$pid] = $peserta[$pid];
                }
            }
        }

        // Blokir hanya untuk hari ini; absensi tanggal lain hanya dicatat.
        if ($tanggal === date('Y-m-d')) {
            $result['diblokir'] = self::apply($blokir, true, $tanggal, $result['gagal']);
        }
        $result['dibuka'] = self::apply($buka, false, $tanggal, $result['gagal']);

        return $result;
    }

    /** Buka blokir satu siswa (dari laporan admin) tanpa mengubah status kehadirannya. */
    public static function unblock(int $pesertaId, string $tanggal): bool
    {
        $p = db_connect()->table('peserta')->select('id, username')->where('id', $pesertaId)->get()->getRowArray();
        if (! $p) {
            return false;
        }
        $gagal = [];

        return self::apply([$p['id'] => $p['username']], false, $tanggal, $gagal) === 1;
    }

    /** Dipanggil cron: buka blokir dari hari-hari sebelumnya. */
    public static function unblockPast(): int
    {
        $db    = db_connect();
        $rows  = $db->table('absensi a')->select('a.tanggal, a.peserta_id, p.username')
            ->join('peserta p', 'p.id = a.peserta_id')
            ->where('a.diblokir', 1)->where('a.tanggal <', date('Y-m-d'))
            ->get()->getResultArray();
        $count = 0;
        $gagal = [];
        foreach ($rows as $r) {
            $count += self::apply([$r['peserta_id'] => $r['username']], false, $r['tanggal'], $gagal);
        }

        return $count;
    }

    /** Rekap per ruang untuk satu tanggal. */
    public static function rekap(string $tanggal): array
    {
        $db = db_connect();

        return $db->query(
            "SELECT r.id, r.nama, r.pengawas,
                    COUNT(p.id) AS total,
                    SUM(a.status = 'hadir') AS hadir,
                    SUM(a.status = 'tidak_hadir') AS tidak_hadir,
                    SUM(a.diblokir = 1) AS diblokir,
                    MAX(a.updated_at) AS terakhir
               FROM ruang r
               LEFT JOIN peserta p ON p.ruang_id = r.id
               LEFT JOIN absensi a ON a.peserta_id = p.id AND a.tanggal = ?
              GROUP BY r.id, r.nama, r.pengawas
              ORDER BY LENGTH(r.nama), r.nama",
            [$tanggal]
        )->getResultArray();
    }

    /**
     * Suspend/buka akun Moodle untuk peserta (id => username), lalu catat flag diblokir.
     *
     * @return int jumlah yang berhasil
     */
    private static function apply(array $peserta, bool $suspend, string $tanggal, array &$gagal): int
    {
        if ($peserta === []) {
            return 0;
        }
        $db = db_connect();
        if (! $suspend) {
            // Siswa yang diblokir karena pelanggaran tetap disuspend; cukup lepaskan tanda absensinya.
            foreach (Blokir::aktifIds(array_keys($peserta)) as $pid) {
                $db->table('absensi')->where('tanggal', $tanggal)->where('peserta_id', $pid)->update(['diblokir' => 0]);
                unset($peserta[$pid]);
            }
            if ($peserta === []) {
                return 0;
            }
        }
        $ids = array_column(
            Moodle::db()->table(Moodle::t('user'))->select('id, username')->where('deleted', 0)->whereIn('username', array_values($peserta))->get()->getResultArray(),
            'id',
            'username'
        );
        $byMoodle = [];
        foreach ($peserta as $pid => $username) {
            if (isset($ids[$username])) {
                $byMoodle[(int) $ids[$username]] = $pid;
            } elseif (! $suspend) {
                // Tidak ada di Moodle: cukup lepaskan tandanya.
                $db->table('absensi')->where('tanggal', $tanggal)->where('peserta_id', $pid)->update(['diblokir' => 0]);
            } else {
                $gagal[] = $username . ' belum ada di Moodle';
            }
        }
        if ($byMoodle === []) {
            return 0;
        }

        $ok = 0;
        foreach (MoodleBridge::call('suspend_users', ['userids' => array_keys($byMoodle), 'suspend' => $suspend]) as $item) {
            $pid = $byMoodle[(int) $item['userid']] ?? null;
            if (isset($item['error']) || $pid === null) {
                $gagal[] = ($pid !== null ? $peserta[$pid] : 'user ' . $item['userid']) . ': ' . ($item['error'] ?? 'tidak dikenal');

                continue;
            }
            $db->table('absensi')->where('tanggal', $tanggal)->where('peserta_id', $pid)->update(['diblokir' => $suspend ? 1 : 0]);
            $ok++;
        }

        return $ok;
    }
}
