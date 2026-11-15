<?php

namespace App\Libraries;

/**
 * Log untuk Tools → Log aktivitas: kapan pengawas mengabsen (per ruang) dan kapan siswa mulai ujian
 * (waktu attempt dibuat = soal nomor 1 pertama kali tampil, `quiz_attempts.timestart`).
 */
class LogUjian
{
    /**
     * Absensi pengawas pada tanggal itu, satu baris per ruang (ruang yang belum mengabsen ikut, waktunya kosong).
     * Diambil dari activity_log aksi "absensi" (setiap simpan tercatat; updated_at absensi tertimpa saat simpan ulang).
     *
     * @return list<array{ruang:string, pengawas:string, akun:string, pertama:?string, terakhir:?string, kali:int, detail:string, oleh:string}>
     */
    public static function pengawas(string $tanggal): array
    {
        $db    = db_connect();
        $ruang = $db->query(
            "SELECT r.id, r.nama, r.pengawas, a.username AS akun
               FROM ruang r LEFT JOIN admins a ON a.ruang_id = r.id AND a.role = 'proktor'
              ORDER BY LENGTH(r.nama), r.nama"
        )->getResultArray();

        // Login pengawas ke panel hari itu: jam pertama + IP.
        $login = [];
        foreach ($db->table('activity_log')->select('username, ip, created_at')->where('aksi', 'login')
            ->where('created_at >=', $tanggal . ' 00:00:00')->where('created_at <=', $tanggal . ' 23:59:59')
            ->orderBy('id', 'ASC')->get()->getResultArray() as $l) {
            $login[$l['username']] ??= ['jam' => $l['created_at'], 'ip' => $l['ip'], 'kali' => 0];
            $login[$l['username']]['kali']++;
        }

        $rows = [];
        foreach ($ruang as $r) {
            $rows[$r['nama']] = [
                'ruang' => $r['nama'], 'pengawas' => (string) $r['pengawas'], 'akun' => (string) $r['akun'],
                'pertama' => null, 'terakhir' => null, 'kali' => 0, 'detail' => '', 'oleh' => '',
                'login' => $login[(string) $r['akun']] ?? null,
            ];
        }

        $logs = $db->table('activity_log')->select('username, detail, created_at')
            ->where('aksi', 'absensi')->where('created_at >=', $tanggal . ' 00:00:00')->where('created_at <=', $tanggal . ' 23:59:59')
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($logs as $l) {
            // Detail: "ruang <nama> <Y-m-d>: n hadir, n tidak hadir, ..."
            if (! preg_match('/^ruang (.+?) \d{4}-\d{2}-\d{2}: (.*)$/u', (string) $l['detail'], $m)) {
                continue;
            }
            $nama = $m[1];
            $rows[$nama] ??= ['ruang' => $nama, 'pengawas' => '', 'akun' => '', 'pertama' => null, 'terakhir' => null, 'kali' => 0, 'detail' => '', 'oleh' => '', 'login' => null];
            $rows[$nama]['pertama'] ??= $l['created_at'];
            $rows[$nama]['terakhir'] = $l['created_at'];
            $rows[$nama]['kali']++;
            $rows[$nama]['detail'] = $m[2];
            $rows[$nama]['oleh']   = (string) $l['username'];
            // Akun pengawas bisa sudah dibuat ulang (kode baru): pakai login akun yang mengabsen.
            $rows[$nama]['login'] ??= $login[(string) $l['username']] ?? null;
        }

        return array_values($rows);
    }

    /**
     * Siswa yang mulai ujian pada tanggal itu (attempt bukan pratinjau), terbaru di atas.
     *
     * @return list<array{mulai:int, selesai:?int, state:string, username:string, nama:string, kelas:string, ruang:string, mapel:string, ujian:string}>
     */
    public static function siswa(string $tanggal): array
    {
        $awal  = strtotime($tanggal . ' 00:00:00');
        $a     = Moodle::t('quiz_attempts');
        $q     = Moodle::t('quiz');
        $c     = Moodle::t('course');
        $u     = Moodle::t('user');
        $rows  = Moodle::db()->query(
            "SELECT qa.timestart, qa.timefinish, qa.state, u.username, u.firstname, u.lastname, q.name AS ujian, c.shortname AS mapel
               FROM {$a} qa
               JOIN {$q} q ON q.id = qa.quiz
               JOIN {$c} c ON c.id = q.course
               JOIN {$u} u ON u.id = qa.userid
              WHERE qa.preview = 0 AND qa.timestart >= ? AND qa.timestart < ?
              ORDER BY qa.timestart DESC",
            [$awal, $awal + 86400]
        )->getResultArray();
        if ($rows === []) {
            return [];
        }

        $peserta = array_column(db_connect()->table('peserta p')
            ->select('p.username, p.nama, p.kelas, r.nama AS ruang')
            ->join('ruang r', 'r.id = p.ruang_id', 'left')
            ->whereIn('p.username', array_unique(array_column($rows, 'username')))
            ->get()->getResultArray(), null, 'username');

        $jamMulai = self::jamMulai($tanggal);

        return array_map(static function (array $r) use ($peserta, $jamMulai): array {
            $p     = $peserta[$r['username']] ?? null;
            $kelas = $p['kelas'] ?? (string) $r['lastname'];
            $acuan = $jamMulai[Tingkat::of($kelas)] ?? null;

            return [
                // Menit terlambat dibanding jam ujian tingkatannya (Tools → Info Kegiatan); null bila jam tidak diisi.
                'telat'    => $acuan === null ? null : (int) floor(((int) $r['timestart'] - $acuan) / 60),
                'mulai'    => (int) $r['timestart'],
                'selesai'  => (int) $r['timefinish'] > 0 ? (int) $r['timefinish'] : null,
                'state'    => (string) $r['state'],
                'username' => (string) $r['username'],
                'nama'     => $p['nama'] ?? trim($r['firstname']),
                'kelas'    => $kelas,
                'ruang'    => (string) ($p['ruang'] ?? ''),
                'mapel'    => (string) $r['mapel'],
                'ujian'    => (string) $r['ujian'],
            ];
        }, $rows);
    }

    /** Jam mulai ujian per tingkatan pada tanggal itu (timestamp), dari setting jam_<X|XI|XII>_mulai. */
    private static function jamMulai(string $tanggal): array
    {
        $jam = [];
        foreach (Tingkat::LIST as $t) {
            $v = Settings::get('jam_' . $t . '_mulai');
            if (preg_match('/^\d{2}:\d{2}$/', $v)) {
                $jam[$t] = strtotime($tanggal . ' ' . $v . ':00');
            }
        }

        return $jam;
    }

    /** Data panel tiap peserta (username → nama, kelas, ruang). */
    private static function pesertaMap(?array $usernames = null): array
    {
        $b = db_connect()->table('peserta p')->select('p.id, p.username, p.nama, p.kelas, p.ruang_id, r.nama AS ruang')
            ->join('ruang r', 'r.id = p.ruang_id', 'left');
        if ($usernames !== null) {
            if ($usernames === []) {
                return [];
            }
            $b->whereIn('p.username', $usernames);
        }

        return array_column($b->get()->getResultArray(), null, 'username');
    }

    /**
     * Login/logout siswa (peserta) di Moodle pada tanggal itu, satu baris per siswa.
     *
     * @return list<array{username:string, nama:string, kelas:string, ruang:string, pertama:int, terakhir:int, kali:int, ips:list<string>, logout:?int}>
     */
    public static function loginSiswa(string $tanggal): array
    {
        $awal = strtotime($tanggal . ' 00:00:00');
        $log  = Moodle::db()->query(
            'SELECT u.username, l.eventname, l.ip, l.timecreated
               FROM ' . Moodle::t('logstore_standard_log') . ' l JOIN ' . Moodle::t('user') . ' u ON u.id = l.userid
              WHERE l.eventname IN ? AND l.timecreated >= ? AND l.timecreated < ?
              ORDER BY l.timecreated',
            [['\\core\\event\\user_loggedin', '\\core\\event\\user_loggedout'], $awal, $awal + 86400]
        )->getResultArray();
        $peserta = self::pesertaMap(array_values(array_unique(array_column($log, 'username'))));

        $rows = [];
        foreach ($log as $l) {
            $p = $peserta[$l['username']] ?? null;
            if ($p === null) {
                continue;  // admin / guru
            }
            $rows[$l['username']] ??= [
                'username' => $l['username'], 'nama' => $p['nama'], 'kelas' => $p['kelas'], 'ruang' => (string) $p['ruang'],
                'pertama' => null, 'terakhir' => null, 'kali' => 0, 'ips' => [], 'logout' => null,
            ];
            $r = &$rows[$l['username']];
            if (str_ends_with($l['eventname'], 'user_loggedin')) {
                $r['pertama'] ??= (int) $l['timecreated'];
                $r['terakhir'] = (int) $l['timecreated'];
                $r['kali']++;
                if (! in_array($l['ip'], $r['ips'], true)) {
                    $r['ips'][] = (string) $l['ip'];
                }
            } else {
                $r['logout'] = (int) $l['timecreated'];
            }
            unset($r);
        }

        return array_values(array_filter($rows, static fn ($r) => $r['kali'] > 0));
    }

    /**
     * Siswa yang ditandai hadir oleh pengawas pada tanggal itu tetapi belum memulai ujian apa pun hari itu.
     *
     * @return list<array{username:string, nama:string, kelas:string, ruang:string, dicatat_oleh:string, jam_absen:string, login:bool}>
     */
    public static function belumMulai(string $tanggal): array
    {
        $hadir = db_connect()->query(
            "SELECT p.username, p.nama, p.kelas, r.nama AS ruang, a.dicatat_oleh, a.updated_at
               FROM absensi a JOIN peserta p ON p.id = a.peserta_id LEFT JOIN ruang r ON r.id = p.ruang_id
              WHERE a.tanggal = ? AND a.status = 'hadir'
              ORDER BY LENGTH(r.nama), r.nama, p.kelas, p.nama",
            [$tanggal]
        )->getResultArray();
        if ($hadir === []) {
            return [];
        }

        $awal  = strtotime($tanggal . ' 00:00:00');
        $users = array_column($hadir, 'username');
        $mulai = array_flip(array_column(Moodle::db()->query(
            'SELECT DISTINCT u.username FROM ' . Moodle::t('quiz_attempts') . ' qa JOIN ' . Moodle::t('user') . ' u ON u.id = qa.userid
              WHERE qa.preview = 0 AND qa.timestart >= ? AND qa.timestart < ? AND u.username IN ?',
            [$awal, $awal + 86400, $users]
        )->getResultArray(), 'username'));
        $login = array_flip(array_column(Moodle::db()->query(
            'SELECT DISTINCT u.username FROM ' . Moodle::t('logstore_standard_log') . ' l JOIN ' . Moodle::t('user') . ' u ON u.id = l.userid
              WHERE l.eventname = ? AND l.timecreated >= ? AND l.timecreated < ? AND u.username IN ?',
            ['\\core\\event\\user_loggedin', $awal, $awal + 86400, $users]
        )->getResultArray(), 'username'));

        $rows = [];
        foreach ($hadir as $h) {
            if (! isset($mulai[$h['username']])) {
                $rows[] = [
                    'username' => $h['username'], 'nama' => $h['nama'], 'kelas' => $h['kelas'], 'ruang' => (string) $h['ruang'],
                    'dicatat_oleh' => (string) $h['dicatat_oleh'], 'jam_absen' => substr((string) $h['updated_at'], 11, 5),
                    'login' => isset($login[$h['username']]),
                ];
            }
        }

        return $rows;
    }

    /**
     * Tindakan akun pengawas di panel pada tanggal itu (selain login/logout/absensi yang sudah ada di tabel ruang).
     *
     * @return list<array{waktu:string, akun:string, nama:string, ruang:string, aksi:string, detail:string, ip:string}>
     */
    public static function tindakanPengawas(string $tanggal): array
    {
        return db_connect()->query(
            "SELECT l.created_at AS waktu, l.username AS akun, a.nama, COALESCE(r.nama, '') AS ruang, l.aksi, COALESCE(l.detail, '') AS detail, l.ip
               FROM activity_log l JOIN admins a ON a.username = l.username AND a.role = 'proktor'
               LEFT JOIN ruang r ON r.id = a.ruang_id
              WHERE l.created_at >= ? AND l.created_at <= ? AND l.aksi NOT IN ('login', 'logout', 'absensi')
              ORDER BY l.id DESC",
            [$tanggal . ' 00:00:00', $tanggal . ' 23:59:59']
        )->getResultArray();
    }

    /** Nama event Moodle → keterangan untuk jejak siswa. */
    private const EVENT = [
        '\\core\\event\\user_loggedin'              => ['Login', 'box-arrow-in-right', 'primary'],
        '\\core\\event\\user_loggedout'             => ['Logout', 'box-arrow-right', 'secondary'],
        '\\mod_quiz\\event\\attempt_started'        => ['Mulai ujian (soal 1 tampil)', 'play-circle', 'success'],
        '\\mod_quiz\\event\\attempt_summary_viewed' => ['Membuka ringkasan jawaban', 'list-check', 'secondary'],
        '\\mod_quiz\\event\\attempt_submitted'      => ['Selesai / submit', 'check-circle', 'success'],
        '\\mod_quiz\\event\\attempt_becameoverdue'  => ['Waktu habis', 'hourglass-bottom', 'warning'],
        '\\mod_quiz\\event\\attempt_abandoned'      => ['Attempt ditinggalkan', 'x-circle', 'danger'],
        '\\mod_quiz\\event\\attempt_deleted'        => ['Attempt dihapus', 'trash', 'danger'],
        '\\mod_quiz\\event\\user_override_created'  => ['Tambah waktu (pengaturan khusus dibuat)', 'clock-history', 'info'],
        '\\mod_quiz\\event\\user_override_updated'  => ['Tambah waktu (pengaturan khusus diubah)', 'clock-history', 'info'],
        '\\core\\event\\user_updated'               => ['Akun diubah (mis. diblokir/dibuka)', 'person-gear', 'secondary'],
    ];

    /**
     * Jejak satu siswa pada tanggal itu: event Moodle (login, mulai, submit, tambah waktu, ...) + catatan panel
     * (absensi, blokir, tindakan pengawas yang menyebut username-nya), urut waktu.
     *
     * @return array{peserta:?array, items:list<array{waktu:int, label:string, ikon:string, warna:string, ket:string, sumber:string}>}
     */
    public static function jejak(string $username, string $tanggal): array
    {
        $awal    = strtotime($tanggal . ' 00:00:00');
        $peserta = self::pesertaMap([$username])[$username] ?? null;
        $items   = [];

        $user = Moodle::db()->table(Moodle::t('user'))->select('id')->where('username', $username)->where('deleted', 0)->get()->getRowArray();
        if ($user !== null) {
            $uid = (int) $user['id'];
            $log = Moodle::db()->query(
                'SELECT l.eventname, l.ip, l.timecreated, l.userid, c.shortname AS mapel
                   FROM ' . Moodle::t('logstore_standard_log') . ' l LEFT JOIN ' . Moodle::t('course') . ' c ON c.id = l.courseid
                  WHERE (l.userid = ? OR l.relateduserid = ?) AND l.eventname IN ? AND l.timecreated >= ? AND l.timecreated < ?
                  ORDER BY l.timecreated, l.id',
                [$uid, $uid, array_keys(self::EVENT), $awal, $awal + 86400]
            )->getResultArray();
            foreach ($log as $l) {
                [$label, $ikon, $warna] = self::EVENT[$l['eventname']];
                $ket = [];
                if ($l['mapel'] !== null && str_contains($l['eventname'], 'mod_quiz')) {
                    $ket[] = $l['mapel'];
                }
                if (str_starts_with($l['eventname'], '\\core\\event\\user_log') && $l['ip'] !== '') {
                    $ket[] = 'IP ' . $l['ip'];
                }
                if ((int) $l['userid'] !== $uid) {
                    $ket[] = 'oleh sistem/panel';
                }
                $items[] = ['waktu' => (int) $l['timecreated'], 'label' => $label, 'ikon' => $ikon, 'warna' => $warna, 'ket' => implode(' · ', $ket), 'sumber' => 'Moodle'];
            }
        }

        if ($peserta !== null) {
            $db = db_connect();
            $a  = $db->table('absensi')->where('tanggal', $tanggal)->where('peserta_id', $peserta['id'])->get()->getRowArray();
            if ($a !== null) {
                $items[] = [
                    'waktu' => strtotime((string) $a['updated_at']), 'label' => $a['status'] === 'hadir' ? 'Diabsen hadir' : 'Diabsen tidak hadir',
                    'ikon' => 'clipboard-check', 'warna' => $a['status'] === 'hadir' ? 'success' : 'danger',
                    'ket' => 'oleh ' . $a['dicatat_oleh'] . ((int) $a['diblokir'] === 1 ? ' · diblokir hari ini' : ''), 'sumber' => 'Panel',
                ];
            }
        }
        // Tindakan panel yang menyebut username siswa (blokir, buka blokir, paksa submit, tambah waktu, reset login).
        foreach (db_connect()->table('activity_log')->select('username, aksi, detail, created_at')
            ->where('created_at >=', $tanggal . ' 00:00:00')->where('created_at <=', $tanggal . ' 23:59:59')
            ->like('detail', $username)->orderBy('id')->get()->getResultArray() as $l) {
            if (! preg_match('/(^|[^A-Za-z0-9._-])' . preg_quote($username, '/') . '([^A-Za-z0-9._-]|$)/', (string) $l['detail'])) {
                continue;  // username lain yang memuat teks ini
            }
            $items[] = [
                'waktu' => strtotime((string) $l['created_at']), 'label' => str_replace('_', ' ', ucfirst($l['aksi'])),
                'ikon' => 'person-badge', 'warna' => 'dark', 'ket' => 'oleh ' . $l['username'], 'sumber' => 'Panel',
            ];
        }

        usort($items, static fn ($x, $y) => $x['waktu'] <=> $y['waktu']);

        return ['peserta' => $peserta, 'items' => $items];
    }
}
