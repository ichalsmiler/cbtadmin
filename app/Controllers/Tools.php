<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\MoodleBridge;
use App\Libraries\School;
use App\Libraries\Settings;
use Config\Session as SessionConfig;

class Tools extends PanelController
{
    /** Kunci pengaturan => label di formulir. */
    public const FIELDS = [
        'nama_kegiatan'    => 'Nama Kegiatan',
        'nama_sekolah'     => 'Nama Sekolah',
        'npsn'             => 'NPSN',
        'alamat'           => 'Alamat Sekolah',
        'kota'             => 'Kota (tempat tanda tangan)',
        'kepala_sekolah'   => 'Kepala Sekolah',
        'nip_kepala'       => 'NIP Kepala Sekolah',
        'webmin_url'       => 'URL Webmin',
        'refresh_interval' => 'Interval refresh (detik)',
        'active_minutes'   => 'User dianggap aktif dalam (menit)',
    ];

    private const USERNAME_RE = '/^[a-z0-9._-]{3,60}$/';

    public function index(): string
    {
        $db = db_connect();

        return $this->render('tools/index', [
            'title'   => 'Tools',
            'fields'  => self::FIELDS,
            'values'  => Settings::all(),
            'hasLogo' => School::logoPath() !== null,
            'admins'  => $db->table('admins')->select('id, username, nama, role, aktif, last_login')->orderBy('username')->get()->getResultArray(),
            'meId'    => (int) session()->get('admin_id'),
            'logs'    => $db->table('activity_log')->orderBy('id', 'DESC')->limit(300)->get()->getResultArray(),
        ]);
    }

    // ------------------------------------------------------------------ Pengaturan

    public function save()
    {
        foreach (array_keys(self::FIELDS) as $key) {
            $value = trim((string) $this->request->getPost($key));
            if (in_array($key, ['refresh_interval', 'active_minutes'], true) && $value !== '') {
                $value = (string) max(1, (int) $value);
            }
            Settings::set($key, mb_substr($value, 0, 255));
        }

        $message = 'Pengaturan disimpan.';
        $logo    = $this->request->getFile('logo');
        if ($logo && $logo->getError() !== UPLOAD_ERR_NO_FILE) {
            $type = $logo->isValid() ? $logo->getMimeType() : '';
            if (! in_array($type, ['image/png', 'image/jpeg'], true) || $logo->getSize() > 1024 * 1024) {
                return redirect()->to(site_url('tools'))->with('error', 'Pengaturan disimpan, tetapi logo ditolak: harus PNG atau JPG, maksimal 1 MB.');
            }
            School::removeLogo();
            $name = 'logo.' . ($type === 'image/png' ? 'png' : 'jpg');
            $logo->move(School::UPLOAD_DIR, $name, true);
            Settings::set('logo_file', $name);
            $message = 'Pengaturan dan logo disimpan.';
        } elseif ($this->request->getPost('hapus_logo')) {
            School::removeLogo();
            $message = 'Pengaturan disimpan, logo dihapus.';
        }
        ActivityLog::add('ubah_pengaturan');

        return redirect()->to(site_url('tools'))->with('success', $message);
    }

    public function logo()
    {
        $path = School::logoPath();
        if ($path === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', mime_content_type($path))
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody((string) file_get_contents($path));
    }

    // ------------------------------------------------------------------ Akun admin / proktor

    public function simpanAkun()
    {
        $db       = db_connect();
        $id       = (int) $this->request->getPost('id');
        $me       = (int) session()->get('admin_id');
        $username = strtolower(trim((string) $this->request->getPost('username')));
        $nama     = trim((string) $this->request->getPost('nama'));
        $role     = $this->request->getPost('role') === 'proktor' ? 'proktor' : 'admin';
        $aktif    = $this->request->getPost('aktif') ? 1 : 0;
        $password = (string) $this->request->getPost('password');

        if (! preg_match(self::USERNAME_RE, $username) || $nama === '') {
            return redirect()->to(site_url('tools#akun'))->with('error', 'Nama wajib diisi, username 3–60 karakter huruf kecil, angka, titik, strip, atau garis bawah.');
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
