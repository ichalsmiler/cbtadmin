<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Excel;
use App\Libraries\Moodle;
use App\Libraries\QuizStatus;

class NoAttempt extends PanelController
{
    public function index(): string
    {
        $data = $this->collect();

        return $this->render('noattempt/index', $data + [
            'title'     => 'No Attempt Check',
            'ruangList' => $this->ruangList(),
            'kelasList' => array_column(db_connect()->table('peserta')->distinct()->select('kelas')->where('kelas !=', '')->orderBy('kelas')->get()->getResultArray(), 'kelas'),
        ]);
    }

    public function export()
    {
        $data = $this->collect();
        if (! $data['quiz']) {
            return redirect()->to(site_url('noattempt_check'))->with('error', 'Pilih quiz dulu.');
        }

        $rows = [];
        foreach ($data['rows'] as $i => $r) {
            $rows[] = [$i + 1, $r['nomor_peserta'], $r['username'], $r['nama'], $r['kelas'], $r['ruang'] ?? ''];
        }
        ActivityLog::add('export_noattempt', $data['quiz']['name'] . ': ' . count($rows) . ' peserta');

        $file = 'belum_mengerjakan_' . preg_replace('/[^A-Za-z0-9]+/', '_', $data['quiz']['name']) . '_' . date('Ymd_His') . '.xlsx';

        return $this->response->download($file, Excel::build(['no', 'nomor_peserta', 'username', 'nama', 'kelas', 'ruang'], $rows));
    }

    private function collect(): array
    {
        $quizId  = (int) $this->request->getGet('quiz');
        $ruangId = (string) $this->request->getGet('ruang');
        $kelas   = (string) $this->request->getGet('kelas');
        $data    = [
            'quiz' => null, 'quizzes' => [], 'rows' => [], 'terdaftar' => 0, 'error' => false,
            'quizId' => $quizId, 'ruangId' => $ruangId, 'kelas' => $kelas,
        ];

        try {
            $data['quizzes'] = Moodle::quizzes();
            $data['quiz']    = $quizId > 0 ? QuizStatus::findQuiz($quizId) : null;
            if ($data['quiz']) {
                $all               = QuizStatus::filter(QuizStatus::forQuiz($data['quiz']), $ruangId, $kelas);
                $data['terdaftar'] = count($all);
                $data['rows']      = array_values(array_filter($all, static fn ($r) => $r['status'] === 'notstarted'));
                usort($data['rows'], static fn ($a, $b) => strnatcasecmp((string) $a['ruang'], (string) $b['ruang']) ?: [$a['kelas'], $a['nama']] <=> [$b['kelas'], $b['nama']]);
            }
        } catch (\Throwable $e) {
            $data['error'] = true;
            log_message('error', 'No Attempt Check: ' . $e->getMessage());
        }

        return $data;
    }
}
