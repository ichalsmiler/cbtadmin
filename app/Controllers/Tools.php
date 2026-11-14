<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;
use App\Libraries\School;
use App\Libraries\Settings;
use Config\Session as SessionConfig;

/**
 * Tools (seperti AIOCBT):
 *  - Moodle Tools  : Daftar Test / Mapel, Cron Task, Purge / Delete Caches, Anti Submit
 *  - CBTAdmin Tools: Info Sekolah, Info Kegiatan, Lock login / Resume timer / Anti Newtab, Ganti Password
 *  - Lainnya       : akun panel, pengaturan panel, backup, sesi, log aktivitas
 */
class Tools extends PanelController
{
    /** Kunci pengaturan per bagian formulir. */
    private const BAGIAN = [
        'sekolah'  => ['nama_sekolah', 'npsn', 'jabatan_pimpinan', 'kepala_sekolah', 'jenis_nomor_pimpinan', 'nip_kepala'],
        'kegiatan' => ['nama_kegiatan', 'tahun_ajaran', 'catatan_kartu',
            'jam_X_mulai', 'jam_X_selesai', 'jam_XI_mulai', 'jam_XI_selesai', 'jam_XII_mulai', 'jam_XII_selesai'],
        'panel'    => ['webmin_url', 'refresh_interval', 'active_minutes'],
    ];

    /** Aturan ujian (plugin Moodle local_cbtujian). */
    private const UJIAN = ['lock_login' => 'locklogin', 'resume_timer' => 'resumetimer', 'anti_newtab' => 'antinewtab'];

    private const USERNAME_RE = '/^[A-Za-z0-9._-]{3,60}$/';

    public function index(): string
    {
        $db = db_connect();

        return $this->render('tools/index', [
            'title'   => 'Tools',
            'values'  => Settings::all(),
            'hasLogo' => School::logoPath() !== null,
            'hasTtd'  => School::ttdPath() !== null,
            'admins'  => $db->table('admins')->select('id, username, nama, role, aktif, last_login')->orderBy('role')->orderBy('username')->get()->getResultArray(),
            'meId'    => (int) session()->get('admin_id'),
            'logs'    => $db->table('activity_log')->orderBy('id', 'DESC')->limit(300)->get()->getResultArray(),
        ]);
    }

    // ------------------------------------------------------------------ Info Sekolah / Info Kegiatan / pengaturan panel

    public function save()
    {
        $bagian = (string) $this->request->getPost('bagian');
        if (! isset(self::BAGIAN[$bagian])) {
            return redirect()->to(site_url('tools'))->with('error', 'Bagian pengaturan tidak dikenal.');
        }
        foreach (self::BAGIAN[$bagian] as $key) {
            $value = trim((string) $this->request->getPost($key));
            if (in_array($key, ['refresh_interval', 'active_minutes'], true) && $value !== '') {
                $value = (string) max(1, (int) $value);
            }
            Settings::set($key, mb_substr($value, 0, 255));
        }

        $pesan = ['sekolah' => 'Info sekolah disimpan.', 'kegiatan' => 'Info kegiatan disimpan.', 'panel' => 'Pengaturan panel disimpan.'][$bagian];
        if ($bagian === 'sekolah') {
            foreach (['logo' => 'Logo', 'ttd' => 'Tanda tangan'] as $jenis => $label) {
                $hasil = $this->unggahGambar($jenis);
                if ($hasil === false) {
                    return redirect()->to(site_url('tools#sekolah'))->with('error', 'Info sekolah disimpan, tetapi ' . strtolower($label) . ' ditolak: harus PNG atau JPG, maksimal 1 MB.');
                }
                if ($hasil !== null) {
                    $pesan .= ' ' . $label . ' ' . $hasil . '.';
                }
            }
        }
        ActivityLog::add('ubah_pengaturan', $bagian);

        return redirect()->to(site_url('tools#' . $bagian))->with('success', $pesan);
    }

    /** null = tidak ada perubahan, string = keterangan, false = berkas ditolak. */
    private function unggahGambar(string $jenis): string|false|null
    {
        $file = $this->request->getFile($jenis);
        if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $type = $file->isValid() ? $file->getMimeType() : '';
            if (! in_array($type, ['image/png', 'image/jpeg'], true) || $file->getSize() > 1024 * 1024) {
                return false;
            }
            $jenis === 'logo' ? School::removeLogo() : School::removeTtd();
            $name = $jenis . '.' . ($type === 'image/png' ? 'png' : 'jpg');
            $file->move(School::UPLOAD_DIR, $name, true);
            Settings::set($jenis . '_file', $name);

            return 'disimpan';
        }
        if ($this->request->getPost('hapus_' . $jenis)) {
            $jenis === 'logo' ? School::removeLogo() : School::removeTtd();

            return 'dihapus';
        }

        return null;
    }

    /** Pratinjau logo / tanda tangan di halaman Tools. */
    public function gambar(string $jenis)
    {
        $path = $jenis === 'ttd' ? School::ttdPath() : School::logoPath();
        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path))
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody((string) file_get_contents($path));
    }

    // ------------------------------------------------------------------ Aturan ujian (plugin Moodle local_cbtujian)

    /** Anti Submit (menit) atau Lock login / Resume timer / Anti Newtab. */
    public function ujian()
    {
        $back = site_url('tools');
        if ($this->request->getPost('bagian') === 'antisubmit') {
            $menit = max(0, min(1000, (int) $this->request->getPost('anti_submit')));
            Settings::set('anti_submit', (string) $menit);
            $back .= '#antisubmit';
        } else {
            foreach (array_keys(self::UJIAN) as $key) {
                Settings::set($key, $this->request->getPost($key) ? '1' : '0');
            }
            $back .= '#lockres';
        }

        try {
            $this->kirimAturanUjian();
        } catch (\Throwable $e) {
            return redirect()->to($back)->with('error', 'Disimpan di panel, tetapi gagal diterapkan di Moodle: ' . $e->getMessage());
        }
        ActivityLog::add('aturan_ujian', 'anti submit ' . Settings::get('anti_submit', '0') . ' menit; '
            . implode(', ', array_map(static fn ($k) => $k . '=' . Settings::get($k, '0'), array_keys(self::UJIAN))));

        return redirect()->to($back)->with('success', 'Aturan ujian disimpan dan diterapkan di Moodle.');
    }

    /** Samakan setelan plugin Moodle dengan pengaturan panel. */
    private function kirimAturanUjian(): void
    {
        $menit = (int) Settings::get('anti_submit', '0');
        $data  = ['antisubmit' => $menit >= 1000 ? 0 : $menit];
        foreach (self::UJIAN as $key => $plugin) {
            $data[$plugin] = Settings::get($key, '0') === '1' ? 1 : 0;
        }
        $hasil = MoodleBridge::call('cbtujian_config', $data)[0] ?? [];
        if (empty($hasil['plugin'])) {
            throw new \RuntimeException('plugin local_cbtujian belum terpasang di Moodle (jalankan deploy/setelan-moodle.sh).');
        }
    }

    // ------------------------------------------------------------------ Ganti Password

    public function gantiPassword()
    {
        $password = (string) $this->request->getPost('password');
        if (mb_strlen($password) < 8) {
            return redirect()->to(site_url('tools#password'))->with('error', 'Password minimal 8 karakter.');
        }
        db_connect()->table('admins')->where('id', (int) session()->get('admin_id'))
            ->update(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'password_plain' => null]);
        ActivityLog::add('ganti_password');

        return redirect()->to(site_url('tools#password'))->with('success', 'Password Anda diganti. Catat password baru supaya tidak lupa.');
    }

    // ------------------------------------------------------------------ Cron dan cache Moodle

    public function cron()
    {
        try {
            [$ok, $akhir] = MoodleBridge::cron();
        } catch (\Throwable $e) {
            return redirect()->to(site_url('tools#cron'))->with('error', 'Cron gagal: ' . $e->getMessage());
        }
        ActivityLog::add('cron_moodle', $ok ? 'selesai' : 'gagal');

        return redirect()->to(site_url('tools#cron'))->with($ok ? 'success' : 'error', ($ok ? 'Cron task selesai.' : 'Cron task berhenti dengan galat.') . ($akhir !== '' ? ' ' . $akhir : ''));
    }

    public function cache()
    {
        $hapus = $this->request->getPost('jenis') === 'delete';
        try {
            MoodleBridge::call('purge_caches', ['hapus' => $hapus]);
        } catch (\Throwable $e) {
            return redirect()->to(site_url('tools#cache'))->with('error', 'Gagal membersihkan cache: ' . $e->getMessage());
        }
        ActivityLog::add('cache_moodle', $hapus ? 'delete' : 'purge');

        return redirect()->to(site_url('tools#cache'))->with('success', $hapus ? 'Cache Moodle dihapus dan dibangun ulang.' : 'Cache Moodle dibersihkan (purge).');
    }

    // ------------------------------------------------------------------ Daftar Test / Mapel

    public function testManage(): string
    {
        $kategori = [];
        $mapel    = [];
        $katId    = (int) $this->request->getGet('kat');
        $error    = false;
        try {
            $kategori = array_values(array_filter(Moodle::categories(), static fn ($c) => (int) $c['parent'] === 0));
            if ($katId > 0) {
                $mapel = Moodle::coursesInCategory($katId);
            }
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Kelola Test: ' . $e->getMessage());
        }
        $aktif = null;
        foreach ($kategori as $k) {
            if ((int) $k['id'] === $katId) {
                $aktif = $k;
            }
        }

        return $this->render('tools/test_manage', [
            'title'    => 'Daftar Test & Mapel',
            'kategori' => $kategori,
            'aktif'    => $aktif,
            'mapel'    => $mapel,
            'error'    => $error,
            'moodle'   => rtrim(Moodle::url(), '/'),
        ]);
    }

    public function tambahUjian()
    {
        $nama = trim((string) $this->request->getPost('nama'));
        try {
            $id = (int) (MoodleBridge::call('create_category', ['name' => mb_substr($nama, 0, 255)])[0]['id'] ?? 0);
        } catch (\Throwable $e) {
            return redirect()->to(site_url('tools/test_manage'))->with('error', 'Gagal menambah ujian: ' . $e->getMessage());
        }
        ActivityLog::add('tambah_ujian', $nama);

        return redirect()->to(site_url('tools/test_manage') . '?kat=' . $id)->with('success', 'Ujian "' . $nama . '" ditambahkan.');
    }

    public function tambahMapel()
    {
        $kat       = (int) $this->request->getPost('kategori');
        $fullname  = trim((string) $this->request->getPost('fullname'));
        $shortname = trim((string) $this->request->getPost('shortname'));
        $back      = site_url('tools/test_manage') . '?kat=' . $kat;
        try {
            MoodleBridge::call('create_course', ['category' => $kat, 'fullname' => mb_substr($fullname, 0, 254), 'shortname' => mb_substr($shortname, 0, 100)]);
        } catch (\Throwable $e) {
            return redirect()->to($back)->with('error', 'Gagal menambah pelajaran: ' . $e->getMessage());
        }
        ActivityLog::add('tambah_mapel', $shortname . ' (' . $fullname . ')');

        return redirect()->to($back)->with('success', 'Pelajaran "' . $fullname . '" ditambahkan (tersembunyi sampai diaktifkan di Status Test). Isi soalnya di Moodle.');
    }

    // ------------------------------------------------------------------ Akun panel

    public function simpanAkun()
    {
        $db       = db_connect();
        $id       = (int) $this->request->getPost('id');
        $me       = (int) session()->get('admin_id');
        $username = trim((string) $this->request->getPost('username'));
        $nama     = trim((string) $this->request->getPost('nama'));
        $role     = $this->request->getPost('role') === 'proktor' ? 'proktor' : 'admin';
        $aktif    = $this->request->getPost('aktif') ? 1 : 0;
        $password = (string) $this->request->getPost('password');

        if (! preg_match(self::USERNAME_RE, $username) || $nama === '') {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Nama wajib diisi, username 3–60 karakter huruf, angka, titik, strip, atau garis bawah.');
        }
        if ($db->table('admins')->where('username', $username)->where('id !=', $id)->countAllResults() > 0) {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Username "' . $username . '" sudah dipakai.');
        }
        if (($id === 0 || $password !== '') && mb_strlen($password) < 8) {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Password minimal 8 karakter.');
        }
        if ($id === $me && ($role !== 'admin' || ! $aktif)) {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Anda tidak bisa menurunkan role atau menonaktifkan akun Anda sendiri.');
        }

        $data = ['username' => $username, 'nama' => mb_substr($nama, 0, 120), 'role' => $role, 'aktif' => $aktif];
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            // Password yang diketik admin tidak ditampilkan lagi di Daftar Ruang.
            $data['password_plain'] = null;
        }

        if ($id > 0) {
            if (! $db->table('admins')->where('id', $id)->countAllResults()) {
                return redirect()->to(site_url('tools#akun'))->with('error', 'Akun tidak ditemukan.');
            }
            $db->table('admins')->where('id', $id)->update($data);
            ActivityLog::add('ubah_akun', $username . ($password !== '' ? ' (password diganti)' : ''));
        } else {
            $db->table('admins')->insert($data + ['created_at' => date('Y-m-d H:i:s')]);
            ActivityLog::add('tambah_akun', $username . ' (' . $role . ')');
        }

        return redirect()->to(site_url('tools#akun'))->with('success', 'Akun ' . $username . ' disimpan.');
    }

    public function hapusAkun(int $id)
    {
        $db  = db_connect();
        $row = $db->table('admins')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Akun tidak ditemukan.');
        }
        if ($id === (int) session()->get('admin_id')) {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }
        $db->table('admins')->where('id', $id)->delete();
        ActivityLog::add('hapus_akun', $row['username']);

        return redirect()->to(site_url('tools#akun'))->with('success', 'Akun ' . $row['username'] . ' dihapus.');
    }

    // ------------------------------------------------------------------ Utilitas

    /** Unduh salinan database panel (.sql). Berisi password peserta apa adanya. */
    public function backup()
    {
        $db  = db_connect();
        $sql = "-- Backup database panel CBT Admin\n-- Dibuat " . date('Y-m-d H:i:s') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($db->listTables() as $table) {
            $create = $db->query('SHOW CREATE TABLE `' . $table . '`')->getRowArray();
            $sql   .= "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n";

            $rows = $db->table($table)->get()->getResultArray();
            foreach (array_chunk($rows, 200) as $chunk) {
                $cols   = '`' . implode('`, `', array_keys($chunk[0])) . '`';
                $values = array_map(static fn ($row) => '(' . implode(', ', array_map(
                    static fn ($v) => $v === null ? 'NULL' : $db->escape((string) $v),
                    $row
                )) . ')', $chunk);
                $sql .= "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $values) . ";\n";
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        ActivityLog::add('backup_database', strlen($sql) . ' byte');

        return $this->response->download('cbtadmin_backup_' . date('Ymd_His') . '.sql', $sql);
    }

    /** Hapus sesi login panel dan sesi Moodle yang sudah kedaluwarsa. */
    public function bersihkanSesi()
    {
        $config  = config(SessionConfig::class);
        $limit   = time() - max(600, (int) $config->expiration);
        $current = session_id();
        $panel   = 0;
        foreach (glob(rtrim($config->savePath, '/') . '/' . $config->cookieName . '*') ?: [] as $file) {
            if (! str_ends_with($file, $current) && filemtime($file) < $limit && @unlink($file)) {
                $panel++;
            }
        }

        try {
            MoodleBridge::call('session_gc', []);
            $moodle = 'sesi Moodle kedaluwarsa juga dibersihkan';
        } catch (\Throwable $e) {
            $moodle = 'sesi Moodle gagal dibersihkan: ' . $e->getMessage();
        }
        ActivityLog::add('bersihkan_sesi', "$panel sesi panel; $moodle");

        return redirect()->to(site_url('tools#utilitas'))->with('success', "$panel sesi panel kedaluwarsa dihapus, $moodle.");
    }
}
