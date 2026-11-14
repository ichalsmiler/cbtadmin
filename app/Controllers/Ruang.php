<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;

class Ruang extends PanelController
{
    public function index(): string
    {
        $db = db_connect();

        $ruang = $db->table('ruang r')
            ->select('r.*, s.nama AS sesi_nama, (SELECT COUNT(*) FROM peserta p WHERE p.ruang_id = r.id) AS jumlah_peserta', false)
            ->join('sesi s', 's.id = r.sesi_id', 'left')
            ->orderBy('LENGTH(r.nama)', '', false)->orderBy('r.nama')
            ->get()->getResultArray();

        return $this->render('ruang/index', [
            'title' => 'Daftar Ruang',
            'ruang' => $ruang,
            'sesi'  => $db->table('sesi')->orderBy('nama')->get()->getResultArray(),
        ]);
    }

    public function save()
    {
        $nama = trim((string) $this->request->getPost('nama'));
        if ($nama === '') {
            return redirect()->back()->with('error', 'Nama ruang wajib diisi.');
        }

        $sesiId = (int) $this->request->getPost('sesi_id');
        $data   = [
            'nama'      => mb_substr($nama, 0, 60),
            'kapasitas' => max(0, (int) $this->request->getPost('kapasitas')),
            'pengawas'  => mb_substr(trim((string) $this->request->getPost('pengawas')), 0, 120),
            'sesi_id'   => $sesiId > 0 ? $sesiId : null,
        ];

        $db = db_connect();
        $id = (int) $this->request->getPost('id');
        if ($id > 0) {
            $db->table('ruang')->where('id', $id)->update($data);
            ActivityLog::add('ubah_ruang', $data['nama']);
        } else {
            $db->table('ruang')->insert($data);
            ActivityLog::add('tambah_ruang', $data['nama']);
        }

        return redirect()->to(site_url('daftar_ruang'))->with('success', 'Ruang disimpan.');
    }

    public function delete(int $id)
    {
        $db  = db_connect();
        $row = $db->table('ruang')->where('id', $id)->get()->getRowArray();
        if ($row) {
            $db->table('peserta')->where('ruang_id', $id)->update(['ruang_id' => null]);
            // Akun pengawas ruang ini ikut dihapus.
            $db->table('admins')->where('ruang_id', $id)->where('role', 'proktor')->delete();
            $db->table('ruang')->where('id', $id)->delete();
            ActivityLog::add('hapus_ruang', $row['nama']);
        }

        return redirect()->to(site_url('daftar_ruang'))->with('success', 'Ruang dihapus.');
    }

    public function saveSesi()
    {
        $nama = trim((string) $this->request->getPost('nama'));
        if ($nama === '') {
            return redirect()->back()->with('error', 'Nama sesi wajib diisi.');
        }

        $time = static fn ($v) => preg_match('/^\d{2}:\d{2}$/', (string) $v) ? $v . ':00' : null;
        db_connect()->table('sesi')->insert([
            'nama'        => mb_substr($nama, 0, 60),
            'jam_mulai'   => $time($this->request->getPost('jam_mulai')),
            'jam_selesai' => $time($this->request->getPost('jam_selesai')),
        ]);
        ActivityLog::add('tambah_sesi', $nama);

        return redirect()->to(site_url('daftar_ruang'))->with('success', 'Sesi ditambahkan.');
    }

    public function deleteSesi(int $id)
    {
        $db = db_connect();
        $db->table('ruang')->where('sesi_id', $id)->update(['sesi_id' => null]);
        $db->table('peserta')->where('sesi_id', $id)->update(['sesi_id' => null]);
        $db->table('sesi')->where('id', $id)->delete();
        ActivityLog::add('hapus_sesi', 'id ' . $id);

        return redirect()->to(site_url('daftar_ruang'))->with('success', 'Sesi dihapus.');
    }
}
