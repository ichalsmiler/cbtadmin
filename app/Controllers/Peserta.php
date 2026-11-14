<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;
use App\Libraries\Pengawas;
use App\Libraries\Settings;

class Peserta extends PanelController
{
    private const USERNAME_RE = '/^[a-z0-9._@-]{2,100}$/';
    private const COLUMNS     = ['nomor_peserta', 'nama', 'kelas', 'username', 'password', 'ruang', 'sesi'];

    // ------------------------------------------------------------------ Data CBTAdmin

    public function cbtadmin(): string
    {
        $db      = db_connect();
        $kelas   = trim((string) $this->request->getGet('kelas'));
        $ruangId = (string) $this->request->getGet('ruang');

        return $this->render('peserta/cbtadmin', [
            'title'       => 'Daftar Peserta · Data CBTAdmin',
            'rows'        => $this->pesertaRows($kelas, $ruangId),
            'kelasList'   => array_column($db->table('peserta')->distinct()->select('kelas')->where('kelas !=', '')->orderBy('kelas')->get()->getResultArray(), 'kelas'),
            'ruangList'   => $this->ruangList(),
            'sesiList'    => $db->table('sesi')->orderBy('nama')->get()->getResultArray(),
            'filterKelas' => $kelas,
            'filterRuang' => $ruangId,
        ]);
    }

    public function save()
    {
        $db       = db_connect();
        $id       = (int) $this->request->getPost('id');
        $nama     = trim((string) $this->request->getPost('nama'));
        $username = strtolower(trim((string) $this->request->getPost('username')));
        $password = trim((string) $this->request->getPost('password'));

        if ($nama === '' || ! preg_match(self::USERNAME_RE, $username)) {
            return redirect()->back()->with('error', 'Nama wajib diisi, dan username hanya boleh huruf kecil, angka, titik, strip, garis bawah, atau @.');
        }
        if ($db->table('peserta')->where('username', $username)->where('id !=', $id)->countAllResults() > 0) {
            return redirect()->back()->with('error', 'Username "' . $username . '" sudah dipakai peserta lain.');
        }

        $ruangId = (int) $this->request->getPost('ruang_id');
        $sesiId  = (int) $this->request->getPost('sesi_id');
        $data    = [
            'nomor_peserta' => mb_substr(trim((string) $this->request->getPost('nomor_peserta')), 0, 40),
            'nama'          => mb_substr($nama, 0, 150),
            'kelas'         => mb_substr(trim((string) $this->request->getPost('kelas')), 0, 60),
            'username'      => $username,
            'ruang_id'      => $ruangId > 0 ? $ruangId : null,
            'sesi_id'       => $sesiId > 0 ? $sesiId : null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $message = 'Peserta disimpan.';
        if ($id > 0) {
            $old = $db->table('peserta')->where('id', $id)->get()->getRowArray();
            if (! $old) {
                return redirect()->back()->with('error', 'Peserta tidak ditemukan.');
            }
            if ($password !== '' && $password !== $old['password_plain']) {
                $data['password_plain'] = mb_substr($password, 0, 100);
                if ($old['moodle_userid']) {
                    try {
                        $this->resetInMoodle((int) $old['moodle_userid'], $data['password_plain']);
                        $message = 'Peserta disimpan dan password di Moodle ikut diganti.';
                    } catch (\Throwable $e) {
                        return redirect()->back()->with('error', 'Password di Moodle gagal diganti: ' . $e->getMessage());
                    }
                }
            }
            $db->table('peserta')->where('id', $id)->update($data);
            ActivityLog::add('ubah_peserta', $username);
        } else {
            $data['password_plain'] = $password !== '' ? mb_substr($password, 0, 100) : self::randomPassword();
            $data['created_at']     = $data['updated_at'];
            $db->table('peserta')->insert($data);
            ActivityLog::add('tambah_peserta', $username);
        }

        return redirect()->to(site_url('daftar_peserta/cbtadmin'))->with('success', $message);
    }

    public function delete(int $id)
    {
        $db  = db_connect();
        $row = $db->table('peserta')->where('id', $id)->get()->getRowArray();
        if ($row) {
            $db->table('peserta')->where('id', $id)->delete();
            ActivityLog::add('hapus_peserta', $row['username']);
        }

        return redirect()->back()->with('success', 'Peserta dihapus dari Data CBTAdmin.');
    }

    public function bulk()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if ($ids === []) {
            return redirect()->back()->with('error', 'Belum ada peserta yang dicentang.');
        }

        $db = db_connect();
        if ($this->request->getPost('aksi') === 'hapus') {
            $db->table('peserta')->whereIn('id', $ids)->delete();
            ActivityLog::add('hapus_peserta_massal', count($ids) . ' peserta');

            return redirect()->back()->with('success', count($ids) . ' peserta dihapus dari Data CBTAdmin.');
        }

        // Nilai kosong = tidak diubah, "0" = dikosongkan.
        $data = [];
        foreach (['ruang_id', 'sesi_id'] as $field) {
            $value = (string) $this->request->getPost($field);
            if ($value !== '') {
                $data[$field] = (int) $value > 0 ? (int) $value : null;
            }
        }
        if ($data === []) {
            return redirect()->back()->with('error', 'Pilih ruang atau sesi yang akan diterapkan.');
        }
        $db->table('peserta')->whereIn('id', $ids)->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
        ActivityLog::add('atur_ruang_sesi', count($ids) . ' peserta');

        return redirect()->back()->with('success', 'Ruang/sesi diterapkan ke ' . count($ids) . ' peserta.');
    }

    public function template()
    {
        $data = Excel::build(self::COLUMNS, [
            ['0001', 'Contoh Siswa', 'XII IPA 1', 'siswa0001', '', 'Ruang 1', 'Sesi 1'],
        ]);

        return $this->response->download('template_peserta.xlsx', $data);
    }

    public function export()
    {
        $rows = [];
        foreach ($this->pesertaRows((string) $this->request->getGet('kelas'), (string) $this->request->getGet('ruang')) as $p) {
            $rows[] = [$p['nomor_peserta'], $p['nama'], $p['kelas'], $p['username'], $p['password_plain'], $p['ruang_nama'], $p['sesi_nama']];
        }
        ActivityLog::add('export_peserta', count($rows) . ' baris');

        return $this->response->download('peserta_' . date('Ymd_His') . '.xlsx', Excel::build(self::COLUMNS, $rows));
    }

    public function import()
    {
        $file = $this->request->getFile('berkas');
        if (! $file || ! $file->isValid() || ! in_array(strtolower($file->getClientExtension()), ['xlsx', 'xls', 'csv'], true)) {
            return redirect()->back()->with('error', 'Pilih berkas Excel (.xlsx, .xls) atau .csv yang valid.');
        }

        try {
            $rows = array_map(static fn (array $row) => self::normalizeImportRow($row), Excel::read($file->getTempName(), ['nama', 'nama_peserta']));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Berkas tidak bisa dibaca: ' . $e->getMessage());
        }
        if ($rows === [] || ! array_key_exists('nama', $rows[0])) {
            return redirect()->back()->with('error', 'Kolom "Nama" / "Nama Peserta" tidak ditemukan. Pakai template CBT Admin atau template AIOCBT.');
        }

        $db    = db_connect();
        $maps  = [
            'ruang' => array_change_key_case(array_column($db->table('ruang')->get()->getResultArray(), 'id', 'nama')),
            'sesi'  => array_change_key_case(array_column($db->table('sesi')->get()->getResultArray(), 'id', 'nama')),
        ];
        $now   = date('Y-m-d H:i:s');
        $baru  = $ubah = 0;
        $gagal = [];
        $taken = [];  // username yang sudah dipakai baris lain di berkas ini

        foreach ($rows as $i => $row) {
            $line     = $i + 2;
            $username = strtolower($row['username'] ?? '');
            if ($username === '' && ($row['nama'] ?? '') !== '') {
                // Username boleh kosong (seperti template AIOCBT): pakai milik peserta yang sama, atau buat baru.
                $username = $this->autoUsername($row['nama'], $row['kelas'] ?? '', $taken);
            }
            $taken[$username] = true;
            if (($row['nama'] ?? '') === '' || ! preg_match(self::USERNAME_RE, $username)) {
                $gagal[] = $line;

                continue;
            }

            $data = [
                'nomor_peserta' => mb_substr($row['nomor_peserta'] ?? '', 0, 40),
                'nama'          => mb_substr($row['nama'], 0, 150),
                'kelas'         => mb_substr($row['kelas'] ?? '', 0, 60),
                'username'      => $username,
                'updated_at'    => $now,
            ];
            foreach (['ruang', 'sesi'] as $table) {
                $name = $row[$table] ?? '';
                if ($name === '') {
                    continue;
                }
                $key = strtolower($name);
                if (! isset($maps[$table][$key])) {
                    $db->table($table)->insert(['nama' => mb_substr($name, 0, 60)]);
                    $maps[$table][$key] = $db->insertID();
                }
                $data[$table . '_id'] = (int) $maps[$table][$key];
            }
            if (($row['password'] ?? '') !== '') {
                $data['password_plain'] = mb_substr($row['password'], 0, 100);
            }

            $old = $db->table('peserta')->where('username', $username)->get()->getRowArray();
            if ($old) {
                $db->table('peserta')->where('id', $old['id'])->update($data);
                $ubah++;
            } else {
                $data['password_plain'] ??= self::randomPassword();
                $data['created_at']       = $now;
                $db->table('peserta')->insert($data);
                $baru++;
            }
        }
        // Satu akun pengawas untuk setiap ruang (ruang baru dari import ini ikut dibuatkan).
        $pengawas = Pengawas::sync();
        ActivityLog::add('import_peserta', "$baru baru, $ubah diperbarui, " . count($gagal) . " gagal, $pengawas akun pengawas");

        $message = "$baru peserta baru, $ubah diperbarui.";
        if ($pengawas > 0) {
            $message .= " $pengawas akun pengawas dibuat (lihat menu Pengawas).";
        }
        if ($gagal !== []) {
            $message .= ' ' . count($gagal) . ' baris dilewati karena nama kosong atau username tidak valid (baris ' . implode(', ', array_slice($gagal, 0, 15)) . (count($gagal) > 15 ? ', …' : '') . ').';
        }

        return redirect()->to(site_url('daftar_peserta/cbtadmin'))->with($gagal === [] ? 'success' : 'error', $message);
    }

    // ------------------------------------------------------------------ Data Moodle

    public function moodle(): string
    {
        $rows  = [];
        $error = false;

        try {
            $rows = $this->moodleUsers();
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Data Moodle: ' . $e->getMessage());
        }

        $db     = db_connect();
        $linked = array_column($db->table('peserta')->select('username')->get()->getResultArray(), 'username');

        return $this->render('peserta/moodle', [
            'title'       => 'Daftar Peserta · Data Moodle',
            'rows'        => $rows,
            'moodleError' => $error,
            'linked'      => array_flip($linked),
            'pending'     => $db->table('peserta')->where('moodle_userid', null)->countAllResults(),
        ]);
    }

    /** Salin user Moodle yang belum ada di Data CBTAdmin, dan tautkan yang sudah ada. */
    public function sync()
    {
        try {
            $users = $this->moodleUsers();
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Database Moodle tidak bisa dibaca.');
        }

        $db   = db_connect();
        $have = array_column($db->table('peserta')->select('id, username, moodle_userid')->get()->getResultArray(), null, 'username');
        $now  = date('Y-m-d H:i:s');
        $baru = $taut = 0;

        foreach ($users as $u) {
            if (isset($have[$u['username']])) {
                if ((int) $have[$u['username']]['moodle_userid'] !== (int) $u['id']) {
                    $db->table('peserta')->where('id', $have[$u['username']]['id'])->update(['moodle_userid' => $u['id']]);
                    $taut++;
                }

                continue;
            }
            $kelas = trim(explode(',', (string) $u['cohort'])[0]);
            $last  = trim((string) $u['lastname']);
            $db->table('peserta')->insert([
                'nama'          => mb_substr(trim($u['firstname'] . (in_array($last, ['', '-', '.', $kelas], true) ? '' : ' ' . $last)), 0, 150),
                'kelas'         => mb_substr($kelas, 0, 60),
                'username'      => $u['username'],
                'moodle_userid' => $u['id'],
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $baru++;
        }
        ActivityLog::add('sinkron_moodle', "$baru baru, $taut ditautkan");

        return redirect()->back()->with('success', "$baru user Moodle disalin ke Data CBTAdmin, $taut ditautkan. Password user yang disalin tidak diketahui panel; reset dulu bila perlu dicetak di kartu.");
    }

    /** Buat akun Moodle untuk peserta Data CBTAdmin yang belum punya. */
    public function createMoodle()
    {
        $db      = db_connect();
        $pending = $db->table('peserta')->where('moodle_userid', null)->orderBy('id')->get()->getResultArray();
        if ($pending === []) {
            return redirect()->back()->with('success', 'Semua peserta Data CBTAdmin sudah punya akun Moodle.');
        }

        $domain = Settings::get('email_domain', 'cbt.local');
        $users  = [];
        foreach ($pending as $p) {
            if ((string) $p['password_plain'] === '') {
                $p['password_plain'] = self::randomPassword();
                $db->table('peserta')->where('id', $p['id'])->update(['password_plain' => $p['password_plain']]);
            }
            $users[] = [
                'username'  => $p['username'],
                'password'  => $p['password_plain'],
                'firstname' => $p['nama'],
                'lastname'  => $p['kelas'] !== '' ? $p['kelas'] : '-',
                'email'     => str_contains($p['username'], '@') ? $p['username'] : $p['username'] . '@' . $domain,
                'cohort'    => $p['kelas'],
            ];
        }

        try {
            $items = MoodleBridge::call('create_users', ['users' => $users]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membuat user Moodle: ' . $e->getMessage());
        }

        $dibuat = $diperbarui = 0;
        $gagal  = [];
        foreach ($items as $item) {
            if (isset($item['error']) || empty($item['id'])) {
                $gagal[] = $item['username'] . ' (' . ($item['error'] ?? 'tanpa keterangan') . ')';

                continue;
            }
            $db->table('peserta')->where('username', $item['username'])->update(['moodle_userid' => $item['id']]);
            $item['status'] === 'dibuat' ? $dibuat++ : $diperbarui++;
        }
        ActivityLog::add('buat_user_moodle', "$dibuat dibuat, $diperbarui diperbarui, " . count($gagal) . ' gagal');

        $message = "$dibuat user Moodle dibuat, $diperbarui user yang sudah ada ditautkan dan passwordnya disamakan.";
        if ($gagal !== []) {
            $message .= ' Gagal: ' . implode('; ', array_slice($gagal, 0, 5)) . (count($gagal) > 5 ? '; …' : '');
        }

        return redirect()->back()->with($gagal === [] ? 'success' : 'error', $message);
    }

    public function resetPassword()
    {
        $userId   = (int) $this->request->getPost('userid');
        $password = trim((string) $this->request->getPost('password'));
        $password = $password !== '' ? mb_substr($password, 0, 100) : self::randomPassword();

        try {
            $this->resetInMoodle($userId, $password);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Reset password gagal: ' . $e->getMessage());
        }

        db_connect()->table('peserta')->where('moodle_userid', $userId)->update(['password_plain' => $password, 'updated_at' => date('Y-m-d H:i:s')]);
        $username = (string) $this->request->getPost('username');
        ActivityLog::add('reset_password', $username . ' (id ' . $userId . ')');

        return redirect()->back()->with('success', 'Password ' . $username . ' sekarang: ' . $password);
    }

    // ------------------------------------------------------------------ Bantuan

    private function resetInMoodle(int $userId, string $password): void
    {
        $items = MoodleBridge::call('reset_passwords', ['users' => [['id' => $userId, 'password' => $password]]]);
        if (isset($items[0]['error'])) {
            throw new \RuntimeException($items[0]['error']);
        }
    }

    private function pesertaRows(string $kelas, string $ruangId): array
    {
        $builder = db_connect()->table('peserta p')
            ->select('p.*, r.nama AS ruang_nama, s.nama AS sesi_nama')
            ->join('ruang r', 'r.id = p.ruang_id', 'left')
            ->join('sesi s', 's.id = p.sesi_id', 'left');
        if ($kelas !== '') {
            $builder->where('p.kelas', $kelas);
        }
        if ($ruangId === '0') {
            $builder->where('p.ruang_id', null);
        } elseif ($ruangId !== '') {
            $builder->where('p.ruang_id', (int) $ruangId);
        }

        return $builder->orderBy('p.kelas')->orderBy('p.nama')->get()->getResultArray();
    }

    private function moodleUsers(): array
    {
        $user    = Moodle::t('user');
        $members = Moodle::t('cohort_members');
        $cohort  = Moodle::t('cohort');

        return Moodle::db()->query(
            "SELECT u.id, u.username, u.firstname, u.lastname, u.email, u.lastaccess, u.suspended,
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ')
                       FROM {$members} cm JOIN {$cohort} c ON c.id = cm.cohortid
                      WHERE cm.userid = u.id) AS cohort
               FROM {$user} u
              WHERE u.deleted = 0 AND u.id NOT IN ?
              ORDER BY u.username",
            [Moodle::nonParticipantIds()]
        )->getResultArray();
    }

    /**
     * Password berbentuk "Kmtr-482": memenuhi kebijakan password bawaan Moodle
     * (8 karakter, huruf besar, huruf kecil, angka, simbol) dan tetap mudah diketik.
     * Karakter yang mudah tertukar (0/O, 1/l/I) tidak dipakai.
     */
    /** Samakan nama kolom template AIOCBT dengan kolom CBT Admin. */
    private static function normalizeImportRow(array $row): array
    {
        $alias = [
            'nama_peserta' => 'nama',
            'nama_rombel'  => 'kelas',
            'rombel'       => 'kelas',
            'kel_sesi'     => 'sesi',
            'kelompok_sesi' => 'sesi',
            'no_peserta'   => 'nomor_peserta',
            'nomor'        => 'nomor_peserta',
        ];
        foreach ($alias as $from => $to) {
            if (isset($row[$from]) && ($row[$to] ?? '') === '') {
                $row[$to] = $row[$from];
            }
        }
        // Rombel kosong: pakai kolom "Kelas / Jurusan".
        if (($row['kelas'] ?? '') === '' && ($row['kelas_jurusan'] ?? '') !== '') {
            $row['kelas'] = $row['kelas_jurusan'];
        }

        return $row;
    }

    /**
     * Username untuk baris tanpa username: milik peserta dengan nama dan kelas yang sama
     * (supaya import ulang tidak membuat peserta ganda), atau "peserta0001" dst.
     * Dua siswa bernama sama di berkas yang sama tetap mendapat username berbeda.
     */
    private function autoUsername(string $nama, string $kelas, array $taken): string
    {
        $db = db_connect();
        foreach ($db->table('peserta')->select('username')->where('nama', $nama)->where('kelas', $kelas)->get()->getResultArray() as $old) {
            if (! isset($taken[$old['username']])) {
                return $old['username'];
            }
        }
        $max = 0;
        foreach ($db->table('peserta')->select('username')->like('username', 'peserta', 'after')->get()->getResultArray() as $r) {
            if (preg_match('/^peserta(\d+)$/', $r['username'], $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('peserta%04d', $max + 1);
    }

    public static function randomPassword(): string
    {
        $pick = static function (string $chars, int $n): string {
            $out = '';
            for ($i = 0; $i < $n; $i++) {
                $out .= $chars[random_int(0, strlen($chars) - 1)];
            }

            return $out;
        };

        return $pick('ABCDEFGHJKMNPQRSTUVWXYZ', 1) . $pick('abcdefghjkmnpqrstuvwxyz', 3) . '-' . $pick('23456789', 3);
    }
}
