<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Moodle;
use App\Libraries\Pdf;
use App\Libraries\QuizStatus;
use App\Libraries\Settings;
use App\Libraries\Tingkat;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Dokumen extends PanelController
{
    // ------------------------------------------------------------------ Kartu / Daftar Hadir

    public function cetak(): string
    {
        $db      = db_connect();
        $quizzes = [];

        try {
            $quizzes = Moodle::quizzes();
        } catch (\Throwable $e) {
            log_message('error', 'Dokumen: ' . $e->getMessage());
        }

        return $this->render('dokumen/cetak', [
            'title'     => 'Kartu / Daftar Hadir',
            'ruangList' => $db->query('SELECT r.id, r.nama, (SELECT COUNT(*) FROM peserta p WHERE p.ruang_id = r.id) AS jumlah FROM ruang r ORDER BY LENGTH(r.nama), r.nama')->getResultArray(),
            'kelasList' => $db->query("SELECT kelas, COUNT(*) AS jumlah FROM peserta GROUP BY kelas ORDER BY kelas")->getResultArray(),
            'sesiList'  => $db->table('sesi')->orderBy('nama')->get()->getResultArray(),
            'quizzes'   => $quizzes,
            'total'     => $db->table('peserta')->countAllResults(),
        ]);
    }

    public function kartu()
    {
        $rows = $this->pesertaCetak();
        if ($rows === []) {
            return redirect()->to(site_url('dokumen/cetak_doc'))->with('error', 'Tidak ada peserta untuk pilihan ini.');
        }

        // Per ruang atau per kelas: tiap ruang/kelas mulai di halaman baru, berlabel di atasnya.
        $perKelas = $this->request->getGet('per') === 'kelas';
        $groups   = [];
        foreach ($rows as $r) {
            $label            = $perKelas ? 'Kelas ' . ($r['kelas'] !== '' ? $r['kelas'] : '(tanpa kelas)') : 'Ruang ' . ($r['ruang_nama'] ?? '(tanpa ruang)');
            $groups[$label][] = $r;
        }
        uksort($groups, 'strnatcasecmp');
        ActivityLog::add('cetak_kartu', count($rows) . ' kartu, per ' . ($perKelas ? 'kelas' : 'ruang') . ', ' . count($groups) . ' kelompok');

        // Jam ujian per tingkatan (Tools → Info Kegiatan) dan QR login Moodle dengan username terisi.
        $jam = [];
        foreach (Tingkat::LIST as $t) {
            $mulai   = Settings::get('jam_' . $t . '_mulai');
            $selesai = Settings::get('jam_' . $t . '_selesai');
            $jam[$t] = $mulai !== '' ? $mulai . ($selesai !== '' ? ' - ' . $selesai : '') : '';
        }
        $qr = new QRCode(new QROptions(['outputInterface' => QRGdImagePNG::class, 'scale' => 3, 'quietzoneSize' => 1, 'outputBase64' => true]));
        foreach ($groups as &$anggota) {
            foreach ($anggota as &$r) {
                $r['jam'] = $jam[Tingkat::of($r['kelas'])] ?? '';
                $r['qr']  = $qr->render(Moodle::url('/login/index.php?username=' . rawurlencode((string) $r['username'])));
            }
            unset($r);
        }
        unset($anggota);

        return Pdf::respond('pdf/kartu', [
            'groups'  => $groups,
            'catatan' => Settings::get('catatan_kartu', 'Kartu peserta wajib dibawa selama ujian berlangsung.'),
        ], 'kartu_peserta_per_' . ($perKelas ? 'kelas' : 'ruang') . '.pdf');
    }

    public function daftarHadir()
    {
        $rows = $this->pesertaCetak();
        if ($rows === []) {
            return redirect()->to(site_url('dokumen/cetak_doc'))->with('error', 'Tidak ada peserta untuk pilihan ruang/sesi ini.');
        }

        $mapel  = '';
        $quizId = (int) $this->request->getGet('quiz');
        if ($quizId > 0) {
            try {
                $mapel = (string) (QuizStatus::findQuiz($quizId)['name'] ?? '');
            } catch (\Throwable $e) {
                log_message('error', 'Daftar hadir: ' . $e->getMessage());
            }
        }

        $groups = [];
        foreach ($rows as $r) {
            $key            = ($r['ruang_nama'] ?? '(tanpa ruang)') . '|' . ($r['sesi_nama'] ?? '');
            $groups[$key][] = $r;
        }
        ActivityLog::add('cetak_daftar_hadir', count($groups) . ' ruang');

        return Pdf::respond('pdf/daftar_hadir', ['groups' => $groups, 'mapel' => $mapel], 'daftar_hadir.pdf');
    }

    /** Peserta Data CBTAdmin menurut ruang/kelas dan sesi, dengan sesi ruang sebagai cadangan. */
    private function pesertaCetak(): array
    {
        $perKelas = $this->request->getGet('per') === 'kelas';
        $ruangId  = $perKelas ? '' : (string) $this->request->getGet('ruang');
        $kelas    = $perKelas ? $this->request->getGet('kelas') : null;
        $sesiId   = (string) $this->request->getGet('sesi');

        $builder = db_connect()->table('peserta p')
            ->select('p.*, r.nama AS ruang_nama, r.pengawas, s.nama AS sesi_nama, s.jam_mulai, s.jam_selesai')
            ->join('ruang r', 'r.id = p.ruang_id', 'left')
            ->join('sesi s', 's.id = COALESCE(p.sesi_id, r.sesi_id)', 'left', false);
        if ($ruangId === '0') {
            $builder->where('p.ruang_id', null);
        } elseif ($ruangId !== '') {
            $builder->where('p.ruang_id', (int) $ruangId);
        }
        if ($kelas !== null && $kelas !== '__semua') {
            $builder->where('p.kelas', (string) $kelas);
        }
        $tingkat = Tingkat::dariRequest();
        if ($tingkat !== '') {
            $builder->where('p.kelas REGEXP ' . db_connect()->escape(Tingkat::regexp($tingkat)), null, false);
        }
        if ($sesiId !== '') {
            $builder->where('COALESCE(p.sesi_id, r.sesi_id)', (int) $sesiId, false);
        }
        if ($perKelas) {
            $builder->orderBy('p.kelas')->orderBy('p.nama');
        } else {
            $builder->orderBy('LENGTH(r.nama)', '', false)->orderBy('r.nama')->orderBy('s.nama')->orderBy('p.nomor_peserta')->orderBy('p.nama');
        }

        return $builder->get()->getResultArray();
    }

    // ------------------------------------------------------------------ Laporan Kehadiran

    public function kehadiran(): string
    {
        return $this->render('dokumen/kehadiran', $this->rekap() + ['title' => 'Laporan Kehadiran']);
    }

    public function kehadiranExport()
    {
        $data = $this->rekap();
        if (! $data['quiz']) {
            return redirect()->to(site_url('dokumen/laporan_kehadiran'))->with('error', 'Pilih mata pelajaran (quiz) dulu.');
        }

        $rows = [];
        foreach ($data['ruang'] as $g) {
            foreach ($g['peserta'] as $r) {
                $rows[] = [$g['nama'], $r['nomor_peserta'], $r['username'], $r['nama'], $r['kelas'], $r['status'] === 'notstarted' ? 'Tidak hadir' : 'Hadir'];
            }
        }
        ActivityLog::add('export_kehadiran', $data['quiz']['name']);

        return $this->response->download(
            'kehadiran_' . preg_replace('/[^A-Za-z0-9]+/', '_', $data['quiz']['name']) . '_' . date('Ymd_His') . '.xlsx',
            Excel::build(['ruang', 'nomor_peserta', 'username', 'nama', 'kelas', 'kehadiran'], $rows)
        );
    }

    public function beritaAcara()
    {
        $data = $this->rekap();
        if (! $data['quiz']) {
            return redirect()->to(site_url('dokumen/laporan_kehadiran'))->with('error', 'Pilih mata pelajaran (quiz) dulu.');
        }
        ActivityLog::add('cetak_berita_acara', $data['quiz']['name']);

        return Pdf::respond('pdf/berita_acara', $data, 'berita_acara_' . $data['quiz']['name'] . '.pdf');
    }

    /** Hadir = punya attempt di quiz itu. Dikelompokkan per ruang Data CBTAdmin. */
    private function rekap(): array
    {
        $quizId  = (int) $this->request->getGet('quiz');
        $ruangId = (string) $this->request->getGet('ruang');
        $tingkat = Tingkat::dariRequest();
        $data    = ['quiz' => null, 'quizzes' => [], 'ruang' => [], 'error' => false, 'quizId' => $quizId, 'ruangId' => $ruangId, 'tingkat' => $tingkat];

        $ruangInfo = array_column(db_connect()->table('ruang r')
            ->select('r.id, r.nama, r.pengawas, s.nama AS sesi_nama, s.jam_mulai, s.jam_selesai')
            ->join('sesi s', 's.id = r.sesi_id', 'left')
            ->get()->getResultArray(), null, 'id');
        $data['ruangList'] = array_values($ruangInfo);

        try {
            $data['quizzes'] = Moodle::quizzes();
            $data['quiz']    = $quizId > 0 ? QuizStatus::findQuiz($quizId) : null;
            if (! $data['quiz']) {
                return $data;
            }

            $groups = [];
            foreach (QuizStatus::filter(QuizStatus::forQuiz($data['quiz']), $ruangId, '', $tingkat) as $r) {
                $key = $r['ruang_id'] ?? 0;
                $groups[$key] ??= [
                    'nama'     => $r['ruang'] ?? '(tanpa ruang)',
                    'pengawas' => $ruangInfo[$key]['pengawas'] ?? '',
                    'sesi'     => $ruangInfo[$key]['sesi_nama'] ?? '',
                    'jam'      => isset($ruangInfo[$key]['jam_mulai']) ? substr((string) $ruangInfo[$key]['jam_mulai'], 0, 5) . '–' . substr((string) $ruangInfo[$key]['jam_selesai'], 0, 5) : '',
                    'peserta'  => [],
                    'hadir'    => 0,
                ];
                $groups[$key]['peserta'][] = $r;
                if ($r['status'] !== 'notstarted') {
                    $groups[$key]['hadir']++;
                }
            }
            foreach ($groups as &$g) {
                usort($g['peserta'], static fn ($a, $b) => [$a['nomor_peserta'], $a['nama']] <=> [$b['nomor_peserta'], $b['nama']]);
                $g['total'] = count($g['peserta']);
                $g['absen'] = array_values(array_filter($g['peserta'], static fn ($r) => $r['status'] === 'notstarted'));
            }
            unset($g);
            uasort($groups, static fn ($a, $b) => strnatcasecmp($a['nama'], $b['nama']));
            $data['ruang'] = array_values($groups);
        } catch (\Throwable $e) {
            $data['error'] = true;
            log_message('error', 'Laporan kehadiran: ' . $e->getMessage());
        }

        return $data;
    }
}
