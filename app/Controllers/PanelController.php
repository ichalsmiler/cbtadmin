<?php

namespace App\Controllers;

use App\Libraries\Settings;

abstract class PanelController extends BaseController
{
    public const VERSION = '1.9.1';

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

    /** Ruang yang diawasi akun yang sedang login; null untuk administrator atau proktor tanpa ruang. */
    protected function myRuang(): ?int
    {
        $ruang = session()->get('admin_ruang');

        return $ruang === null ? null : (int) $ruang;
    }

    /** Daftar ruang untuk pilihan filter: hanya ruangnya sendiri bagi pengawas ruang. */
    protected function ruangList(): array
    {
        $builder = db_connect()->table('ruang')->orderBy('LENGTH(nama)', '', false)->orderBy('nama');
        if ($this->myRuang() !== null) {
            $builder->where('id', $this->myRuang());
        }

        return $builder->get()->getResultArray();
    }
}
