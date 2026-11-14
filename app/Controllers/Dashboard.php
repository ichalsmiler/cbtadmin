<?php

namespace App\Controllers;

use App\Libraries\Moodle;
use App\Libraries\Settings;
use App\Libraries\SysInfo;

class Dashboard extends PanelController
{
    public function index(): string
    {
        return $this->render('dashboard/index', [
            'title'        => 'Dashboard',
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

    private function collect(): array
    {
        $data = ['ruang' => null, 'users' => null, 'aktif' => null, 'belum' => null, 'moodleError' => false];

        try {
            $data['ruang'] = db_connect()->table('ruang')->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard ruang: ' . $e->getMessage());
        }

        try {
            $minutes       = max(1, (int) Settings::get('active_minutes', '5'));
            $data['users'] = Moodle::totalUsers();
            $data['aktif'] = Moodle::activeUsers($minutes);
            $data['belum'] = Moodle::unsubmittedAttempts();
        } catch (\Throwable $e) {
            $data['moodleError'] = true;
            log_message('error', 'Dashboard Moodle: ' . $e->getMessage());
        }

        return $data + ['sys' => SysInfo::get()];
    }
}
