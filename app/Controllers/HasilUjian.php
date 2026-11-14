<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Moodle;
use App\Libraries\Pdf;
use App\Libraries\QuizStatus;
use App\Libraries\Tingkat;

class HasilUjian extends PanelController
{
    public function index(): string
    {
        return $this->render('hasil/index', $this->collect() + [
            'title'     => 'Hasil Ujian',
            'ruangList' => $this->ruangList(),
            'kelasList' => array_column(db_connect()->table('peserta')->distinct()->select('kelas')->where('kelas !=', '')->orderBy('kelas')->get()->getResultArray(), 'kelas'),
        ]);
    }

    public function export()
    {
        $data = $this->collect();
        if (! $data['quiz']) {
            return redirect()->to(site_url('hasil_ujian'))->with('error', 'Pilih quiz dulu.');
        }

        $rows = [];
        foreach ($data['rows'] as $i => $r) {
            $rows[] = [
                $i + 1, $r['nomor_peserta'], $r['username'], $r['nama'], $r['kelas'], $r['ruang'] ?? '',
                self::label($r['status']), $r['nilai'] === null ? '' : number_format($r['nilai'], 2, ',', ''),
                $r['timestart'] ? date('d/m/Y H:i', $r['timestart']) : '', $r['timefinish'] ? date('d/m/Y H:i', $r['timefinish']) : '',
                $r['durasi'] !== null ? self::durasi($r['durasi']) : '',
            ];
        }
        ActivityLog::add('export_hasil', $data['quiz']['name'] . ' (excel)');

        return $this->response->download(
            'hasil_' . preg_replace('/[^A-Za-z0-9]+/', '_', $data['quiz']['name']) . '_' . date('Ymd_His') . '.xlsx',
            Excel::build(['no', 'nomor_peserta', 'username', 'nama', 'kelas', 'ruang', 'status', 'nilai', 'mulai', 'selesai', 'durasi'], $rows)
        );
    }

    public function pdf()
    {
        $data = $this->collect();
        if (! $data['quiz']) {
            return redirect()->to(site_url('hasil_ujian'))->with('error', 'Pilih quiz dulu.');
        }
        ActivityLog::add('export_hasil', $data['quiz']['name'] . ' (pdf)');

        return Pdf::respond('pdf/hasil', $data, 'hasil_' . $data['quiz']['name'] . '.pdf');
    }

    public static function label(string $status): string
    {
        return ['finished' => 'Selesai', 'inprogress' => 'Mengerjakan', 'notstarted' => 'Belum mulai'][$status];
    }

    public static function durasi(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private function collect(): array
    {
        $quizId  = (int) $this->request->getGet('quiz');
        $ruangId = (string) $this->request->getGet('ruang');
        $kelas   = (string) $this->request->getGet('kelas');
        // Parameter "selesai terlalu cepat" (persen): batas waktu quiz, median durasi, nilai minimal.
        $param = [
            'pw' => max(1, min(100, (int) ($this->request->getGet('pw') ?: 25))),
            'pm' => max(1, min(100, (int) ($this->request->getGet('pm') ?: 50))),
            'pn' => max(0, min(100, (int) ($this->request->getGet('pn') ?? 80) ?: 80)),
        ];
        $data    = [
            'quiz' => null, 'quizzes' => [], 'rows' => [], 'stats' => null, 'error' => false,
            'quizId' => $quizId, 'ruangId' => $ruangId, 'kelas' => $kelas, 'tingkat' => Tingkat::dariRequest(),
            'tab' => $this->request->getGet('tab') === 'cepat' ? 'cepat' : 'semua', 'param' => $param, 'cepat' => [], 'median' => null,
        ];

        try {
            $data['quizzes'] = Moodle::quizzes();
            $data['quiz']    = $quizId > 0 ? QuizStatus::findQuiz($quizId) : null;
            if (! $data['quiz']) {
                return $data;
            }

            // Nilai akhir menurut metode penilaian quiz (tertinggi/rata-rata/dst.) dari gradebook quiz.
            $grades = array_column(Moodle::db()->table(Moodle::t('quiz_grades'))
                ->select('userid, grade')->where('quiz', $quizId)->get()->getResultArray(), 'grade', 'userid');

            $semua = QuizStatus::forQuiz($data['quiz']);
            // Median durasi dihitung dari semua peserta quiz ini yang sudah selesai (tidak terpengaruh filter).
            $durasiSemua = [];
            foreach ($semua as $s) {
                if ($s['status'] === 'finished' && $s['timefinish'] > $s['timestart']) {
                    $durasiSemua[] = $s['timefinish'] - $s['timestart'];
                }
            }
            sort($durasiSemua);
            $n      = count($durasiSemua);
            $median = $n ? ($n % 2 ? $durasiSemua[intdiv($n, 2)] : intdiv($durasiSemua[$n / 2 - 1] + $durasiSemua[$n / 2], 2)) : null;
            $data['median'] = $median;
            $limit = (int) $data['quiz']['timelimit'];
            $skala = (float) $data['quiz']['grade'];

            $rows = QuizStatus::filter($semua, $ruangId, $kelas, Tingkat::dariRequest());
            foreach ($rows as &$r) {
                $r['nilai']  = isset($grades[$r['userid']]) ? (float) $grades[$r['userid']] : null;
                $r['durasi'] = $r['status'] === 'finished' && $r['timefinish'] > 0 ? $r['timefinish'] - $r['timestart'] : null;
                // Tanda curiga: durasi pendek (dibanding batas waktu atau median) dan/atau nilai tinggi.
                $r['curiga'] = null;
                if ($r['durasi'] !== null) {
                    $cepat = ($limit > 0 && $r['durasi'] < $limit * $param['pw'] / 100)
                        || ($median && $n >= 5 && $r['durasi'] < $median * $param['pm'] / 100);
                    $tinggi = $r['nilai'] !== null && $skala > 0 && $r['nilai'] >= $skala * $param['pn'] / 100;
                    $r['curiga'] = $cepat ? ($tinggi ? 'tinggi' : 'sedang') : null;
                }
            }
            unset($r);
            $data['cepat'] = array_values(array_filter($rows, static fn ($r) => $r['curiga'] !== null));
            usort($data['cepat'], static fn ($a, $b) => [$a['curiga'] === 'tinggi' ? 0 : 1, $a['durasi']] <=> [$b['curiga'] === 'tinggi' ? 0 : 1, $b['durasi']]);
            usort($rows, static fn ($a, $b) => [$a['kelas'], $a['nama']] <=> [$b['kelas'], $b['nama']]);
            $data['rows'] = $rows;

            $nilai = array_values(array_filter(array_column($rows, 'nilai'), static fn ($v) => $v !== null));
            $data['stats'] = [
                'peserta' => count($rows),
                'selesai' => count(array_filter($rows, static fn ($r) => $r['status'] === 'finished')),
                'dinilai' => count($nilai),
                'rata'    => $nilai ? array_sum($nilai) / count($nilai) : null,
                'min'     => $nilai ? min($nilai) : null,
                'maks'    => $nilai ? max($nilai) : null,
                'skala'   => (float) $data['quiz']['grade'],
            ];
        } catch (\Throwable $e) {
            $data['error'] = true;
            log_message('error', 'Hasil Ujian: ' . $e->getMessage());
        }

        return $data;
    }
}
