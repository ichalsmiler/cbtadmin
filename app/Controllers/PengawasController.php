<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Pengawas;

/** Menu Pengawas (khusus administrator): satu akun pengawas untuk setiap ruang. */
class PengawasController extends PanelController
{
    public function index(): string
    {
        return $this->render('pengawas/index', [
            'title' => 'Pengawas',
            'rows'  => $this->rows(),
        ]);
    }

    /** Buat akun untuk ruang yang belum punya pengawas. */
    public function generate()
    {
        $created = Pengawas::sync();
        ActivityLog::add('generate_pengawas', $created . ' akun');

        return redirect()->to(site_url('pengawas'))->with('success', $created > 0
            ? "$created akun pengawas dibuat."
            : 'Semua ruang sudah punya akun pengawas.');
    }

    /** Password baru: satu akun (id) atau semua akun pengawas. */
    public function reset()
    {
        $id    = (int) $this->request->getPost('id');
        $count = Pengawas::resetPasswords($id > 0 ? [$id] : []);
        ActivityLog::add('reset_password_pengawas', $id > 0 ? 'id ' . $id : 'semua (' . $count . ')');

        return redirect()->to(site_url('pengawas'))->with('success', "Password baru dibuat untuk $count akun pengawas.");
    }

    public function hapus(int $id)
    {
        $db  = db_connect();
        $row = $db->table('admins')->where('id', $id)->where('role', 'proktor')->get()->getRowArray();
        if (! $row) {
            return redirect()->to(site_url('pengawas'))->with('error', 'Akun pengawas tidak ditemukan.');
        }
        $db->table('admins')->where('id', $id)->delete();
        ActivityLog::add('hapus_pengawas', $row['username']);

        return redirect()->to(site_url('pengawas'))->with('success', 'Akun ' . $row['username'] . ' dihapus. Klik "Buat akun" untuk membuatnya lagi.');
    }

    /** Daftar akun pengawas (Excel) untuk dibagikan ke pengawas. */
    public function export()
    {
        $rows = [];
        foreach ($this->rows() as $r) {
            if ($r['admin_id'] !== null) {
                $rows[] = [$r['ruang_nama'], $r['jumlah_peserta'], $r['nama'], $r['username'], $r['password_plain']];
            }
        }
        ActivityLog::add('export_pengawas', count($rows) . ' akun');

        return $this->response->download(
            'akun_pengawas_' . date('Ymd_His') . '.xlsx',
            Excel::build(['Ruang', 'Jumlah Peserta', 'Nama Pengawas', 'Username', 'Password'], $rows)
        );
    }

    private function rows(): array
    {
        return db_connect()->table('ruang r')
            ->select('r.id AS ruang_id, r.nama AS ruang_nama,
                      (SELECT COUNT(*) FROM peserta p WHERE p.ruang_id = r.id) AS jumlah_peserta,
                      a.id AS admin_id, a.username, a.nama, a.password_plain, a.aktif, a.last_login', false)
            ->join('admins a', "a.ruang_id = r.id AND a.role = 'proktor'", 'left')
            ->orderBy('LENGTH(r.nama)', '', false)
            ->orderBy('r.nama')
            ->get()->getResultArray();
    }
}
