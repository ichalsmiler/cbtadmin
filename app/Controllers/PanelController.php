<?php

namespace App\Controllers;

use App\Libraries\Settings;

abstract class PanelController extends BaseController
{
    public const VERSION = '1.2.0';

    protected $helpers = ['url', 'form'];

    protected function render(string $view, array $data = []): string
    {
        $session = session();

        return view($view, $data + [
            'title'       => 'CBT Admin',
            'current'     => trim($this->request->getPath(), '/'),
            'namaSekolah' => Settings::get('nama_sekolah', (string) env('cbt.namaSekolah', '')),
            'adminNama'   => (string) $session->get('admin_nama'),
            'adminRole'   => (string) $session->get('admin_role'),
            'version'     => self::VERSION,
        ]);
    }
}
