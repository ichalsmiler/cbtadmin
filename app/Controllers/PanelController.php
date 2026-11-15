<?php

namespace App\Controllers;

use App\Libraries\Settings;

abstract class PanelController extends BaseController
{
    public const VERSION = '2.3.8';

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

    /**
     * Daftar ruang untuk pilihan filter: hanya ruangnya sendiri bagi pengawas ruang.
     * Kolom `kelas` = kelas siswa di ruang itu ("X-1|X-2"), untuk data-kelas (Ruang mengikuti Kelas/Tingkatan).
     */
    protected function ruangList(): array
    {
        $builder = db_connect()->table('ruang')
            ->select("ruang.*, (SELECT GROUP_CONCAT(DISTINCT p.kelas ORDER BY p.kelas SEPARATOR '|') FROM peserta p WHERE p.ruang_id = ruang.id) AS kelas", false)
            ->orderBy('LENGTH(nama)', '', false)->orderBy('nama');
        if ($this->myRuang() !== null) {
            $builder->where('id', $this->myRuang());
        }

        return $builder->get()->getResultArray();
    }
}
