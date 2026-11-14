<?php

namespace App\Controllers;

use App\Libraries\ActiveTest;
use App\Libraries\Moodle;
use App\Libraries\Pengawas;
use App\Libraries\Settings;
use App\Libraries\SysInfo;

class Dashboard extends PanelController
{
    public function index(): string
    {
        return $this->render('dashboard/index', [
            'title'        => 'Dashboard',
            'isAdmin'      => $this->isAdmin(),
            'myRuang'      => $this->myRuang(),
            'stats'        => $this->collect(),
            'namaKegiatan' => Settings::get('nama_kegiatan'),
            'npsn'         => Settings::get('npsn', (string) env('cbt.npsn', '')),
            'serverIp'     => $this->request->getServer('SERVER_ADDR') ?: '-',
            'webminUrl'    => Settings::get('webmin_url', (string) env('cbt.webminUrl', '')),
            'refresh'      => max(5, (int) Settings::get('refresh_interval', '30')),
        ]);
    }

    public function stats()
    {
        return $this->response->setJSON($this->collect());
    }

    private function isAdmin(): bool
    {
        return session()->get('admin_role') === 'admin';
    }

    private function collect(): array
    {
        $data  = ['ruang' => null, 'users' => null, 'aktif' => null, 'belum' => null, 'sudah' => null, 'moodleError' => false];
        $ruang = $this->myRuang();

        try {
            if ($ruang !== null) {
                // Pengawas ruang: nama ruangnya dan jumlah siswa di ruang itu.
                $row           = db_connect()->table('ruang')->select('nama')->where('id', $ruang)->get()->getRowArray();
                $data['ruang'] = (string) ($row['nama'] ?? '–');
                $data['users'] = db_connect()->table('peserta')->where('ruang_id', $ruang)->countAllResults();
            } else {
                $data['ruang'] = db_connect()->table('ruang')->countAllResults();
            }
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard ruang: ' . $e->getMessage());
        }

        try {
            // Hitungan submit mengikuti tes aktif (Status Test); tanpa tes aktif, semua quiz.
            $catId   = ActiveTest::categoryId() ?: null;
            $userIds = $ruang !== null ? Pengawas::moodleIdsInRuang($ruang) : null;
            if ($ruang === null) {
                $minutes       = max(1, (int) Settings::get('active_minutes', '5'));
                $data['users'] = Moodle::totalUsers();
                $data['aktif'] = Moodle::activeUsers($minutes);
            }
            $data['belum'] = Moodle::unsubmittedAttempts($catId, $userIds);
            $data['sudah'] = Moodle::finishedAttempts($catId, $userIds);
        } catch (\Throwable $e) {
            $data['moodleError'] = true;
            log_message('error', 'Dashboard Moodle: ' . $e->getMessage());
        }

        // Informasi server hanya untuk administrator.
        return $this->isAdmin() ? $data + ['sys' => SysInfo::get()] : $data;
    }
}
