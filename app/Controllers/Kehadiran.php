<?php

namespace App\Controllers;

use App\Libraries\Absensi;
use App\Libraries\ActivityLog;
use App\Libraries\Excel;

/**
 * Laporan Kehadiran: pengawas mengabsen siswa ruangnya hari ini (siswa tidak hadir
 * diblokir di Moodle); administrator melihat rekap semua ruang dan membuka blokir.
 */
class Kehadiran extends PanelController
{
    // ------------------------------------------------------------------ Absensi ruang

    public function index()
    {
        $isAdmin = session()->get('admin_role') === 'admin';
        $ruangId = $this->myRuang() ?? (int) $this->request->getGet('ruang');
        $tanggal = $isAdmin ? $this->tanggal() : date('Y-m-d');

        if ($ruangId === 0) {
            if ($isAdmin) {
                return redirect()->to(site_url('dokumen/absensi'));
            }

            return $this->render('kehadiran/index', ['title' => 'Laporan Kehadiran', 'ruang' => null, 'rows' => [], 'tanggal' => $tanggal, 'isAdmin' => false]);
        }

        $ruang = db_connect()->table('ruang')->where('id', $ruangId)->get()->getRowArray();

        return $this->render('kehadiran/index', [
            'title'   => 'Laporan Kehadiran',
            'ruang'   => $ruang,
            'rows'    => $ruang ? Absensi::forRuang($ruangId, $tanggal) : [],
            'tanggal' => $tanggal,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function simpan()
    {
        $isAdmin = session()->get('admin_role') === 'admin';
        $ruangId = $this->myRuang() ?? ($isAdmin ? (int) $this->request->getPost('ruang_id') : 0);
        $tanggal = $isAdmin ? $this->tanggal((string) $this->request->getPost('tanggal')) : date('Y-m-d');
        $back    = site_url('kehadiran') . ($isAdmin ? '?ruang=' . $ruangId . '&tanggal=' . $tanggal : '');
        if ($ruangId === 0) {
            return redirect()->to($back)->with('error', 'Akun ini belum terhubung ke ruang.');
        }

        $statuses = array_filter((array) $this->request->getPost('status'), static fn ($s) => in_array($s, [Absensi::HADIR, Absensi::TIDAK_HADIR], true));
        if ($statuses === []) {
            return redirect()->to($back)->with('error', 'Belum ada siswa yang ditandai.');
        }

        try {
            $r = Absensi::save($ruangId, $tanggal, $statuses, (string) session()->get('admin_user'));
        } catch (\Throwable $e) {
            return redirect()->to($back)->with('error', 'Absensi gagal disimpan: ' . $e->getMessage());
        }
        $ruang = db_connect()->table('ruang')->select('nama')->where('id', $ruangId)->get()->getRowArray();
        ActivityLog::add('absensi', 'ruang ' . ($ruang['nama'] ?? $ruangId) . " $tanggal: {$r['hadir']} hadir, {$r['tidak']} tidak hadir, {$r['diblokir']} diblokir, {$r['dibuka']} dibuka");

        $message = "Absensi disimpan: {$r['hadir']} hadir, {$r['tidak']} tidak hadir.";
        if ($r['diblokir'] > 0) {
            $message .= " {$r['diblokir']} siswa diblokir di Moodle hari ini.";
        }
        if ($r['dibuka'] > 0) {
            $message .= " Blokir {$r['dibuka']} siswa dibuka.";
        }
        if ($r['gagal'] !== []) {
            $message .= ' Gagal: ' . implode('; ', array_slice($r['gagal'], 0, 5));
        }

        return redirect()->to($back)->with($r['gagal'] === [] ? 'success' : 'error', $message);
    }

    // ------------------------------------------------------------------ Rekap admin

    public function rekap(): string
    {
        $tanggal = $this->tanggal();

        return $this->render('dokumen/absensi', [
            'title'   => 'Absensi Ruang',
            'tanggal' => $tanggal,
            'rows'    => Absensi::rekap($tanggal),
        ]);
    }

    public function buka()
    {
        $pid     = (int) $this->request->getPost('peserta_id');
        $tanggal = $this->tanggal((string) $this->request->getPost('tanggal'));
        try {
            $ok = Absensi::unblock($pid, $tanggal);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal membuka blokir: ' . $e->getMessage());
        }
        $p = db_connect()->table('peserta')->select('username')->where('id', $pid)->get()->getRowArray();
        ActivityLog::add('buka_blokir', ($p['username'] ?? $pid) . ' ' . $tanggal);

        return redirect()->back()->with($ok ? 'success' : 'error', $ok
            ? 'Blokir ' . ($p['username'] ?? '') . ' dibuka; siswa bisa login lagi.'
            : 'Blokir tidak bisa dibuka (siswa belum ada di Moodle?).');
    }

    public function export()
    {
        $tanggal = $this->tanggal();
        $rows    = [];
        foreach (Absensi::rekap($tanggal) as $r) {
            foreach (Absensi::forRuang((int) $r['id'], $tanggal) as $p) {
                $rows[] = [
                    $r['nama'], $p['nomor_peserta'], $p['username'], $p['nama'], $p['kelas'],
                    match ($p['status']) { Absensi::HADIR => 'Hadir', Absensi::TIDAK_HADIR => 'Tidak hadir', default => 'Belum diabsen' },
                    (int) $p['diblokir'] ? 'Ya' : '', $p['dicatat_oleh'] ?? '',
                ];
            }
        }
        ActivityLog::add('export_absensi', $tanggal);

        return $this->response->download(
            'absensi_' . $tanggal . '.xlsx',
            Excel::build(['Ruang', 'Nomor Peserta', 'Username', 'Nama', 'Kelas', 'Kehadiran', 'Diblokir', 'Dicatat oleh'], $rows)
        );
    }

    private function tanggal(?string $value = null): string
    {
        $value ??= (string) $this->request->getGet('tanggal');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : date('Y-m-d');
    }
}
