<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Moodle;
use App\Libraries\Pdf;
use App\Libraries\QuizStatus;

class HasilUjian extends PanelController
{
    public function index(): string
    {
        return $this->render('hasil/index', $this->collect() + [
            'title'     => 'Hasil Ujian',
            'ruangList' => db_connect()->table('ruang')->orderBy('nama')->get()->getResultArray(),
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
        $data    = [
            'quiz' => null, 'quizzes' => [], 'rows' => [], 'stats' => null, 'error' => false,
            'quizId' => $quizId, 'ruangId' => $ruangId, 'kelas' => $kelas,
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

            $rows = QuizStatus::filter(QuizStatus::forQuiz($data['quiz']), $ruangId, $kelas);
            foreach ($rows as &$r) {
                $r['nilai']  = isset($grades[$r['userid']]) ? (float) $grades[$r['userid']] : null;
                $r['durasi'] = $r['status'] === 'finished' && $r['timefinish'] > 0 ? $r['timefinish'] - $r['timestart'] : null;
            }
            unset($r);
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
