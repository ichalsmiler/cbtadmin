<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;
use App\Libraries\QuizStatus;
use App\Libraries\Settings;

class StatusPeserta extends PanelController
{
    public const FILTERS = ['inprogress' => 'Sedang Mengerjakan', 'finished' => 'Selesai', 'notstarted' => 'Belum Mulai'];

    public function index(string $filter = '')
    {
        if ($filter !== '' && ! isset(self::FILTERS[$filter])) {
            return redirect()->to(site_url('status_peserta'));
        }

        $quizId  = (int) $this->request->getGet('quiz');
        $ruangId = (string) $this->request->getGet('ruang');
        $rows    = [];
        $quizzes = [];
        $quiz    = null;
        $error   = false;

        try {
            $quizzes = Moodle::quizzes();
            foreach ($quizzes as $q) {
                if ((int) $q['id'] === $quizId) {
                    $quiz = $q;
                }
            }
            $rows = QuizStatus::filter($quiz ? QuizStatus::forQuiz($quiz) : QuizStatus::allQuizzes(), $ruangId);
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Status Peserta: ' . $e->getMessage());
        }

        $counts = array_fill_keys(array_keys(self::FILTERS), 0);
        $shown  = [];
        foreach ($rows as $row) {
            $counts[$row['status']]++;
            if ($filter === '' || $row['status'] === $filter) {
                $shown[] = $row;
            }
        }

        return $this->render('status/peserta', [
            'title'     => 'Status Peserta',
            'rows'      => $shown,
            'counts'    => $counts,
            'filter'    => $filter,
            'quiz'      => $quiz,
            'quizzes'   => $quizzes,
            'ruangList' => db_connect()->table('ruang')->orderBy('nama')->get()->getResultArray(),
            'ruangId'   => $ruangId,
            'error'     => $error,
            'refresh'   => max(5, (int) Settings::get('refresh_interval', '30')),
            'now'       => time(),
        ]);
    }

    /** Aksi massal untuk pilihan "quizid:userid". */
    public function aksi()
    {
        $pairs = [];
        foreach ((array) $this->request->getPost('ids') as $value) {
            if (preg_match('/^(\d+):(\d+)$/', (string) $value, $m)) {
                $pairs[(int) $m[1]][] = (int) $m[2];
            }
        }
        if ($pairs === []) {
            return redirect()->back()->with('error', 'Belum ada peserta yang dicentang.');
        }

        $aksi = (string) $this->request->getPost('aksi');

        try {
            if ($aksi === 'waktu') {
                $minutes = (int) $this->request->getPost('menit');
                if ($minutes < 1 || $minutes > 600) {
                    return redirect()->back()->with('error', 'Isi tambahan waktu antara 1 dan 600 menit.');
                }
                $items = [];
                foreach ($pairs as $quizId => $userIds) {
                    $items = array_merge($items, MoodleBridge::call('add_time', ['quizid' => $quizId, 'userids' => $userIds, 'minutes' => $minutes]));
                }

                return $this->report($items, "tambah_waktu_{$minutes}_menit", "Waktu ditambah {$minutes} menit untuk %d peserta.");
            }

            if (! in_array($aksi, ['submit', 'hapus'], true)) {
                return redirect()->back()->with('error', 'Aksi tidak dikenal.');
            }
            $attempts = $this->latestAttempts($pairs, $aksi === 'submit' ? ['inprogress', 'overdue'] : null);
            if ($attempts === []) {
                return redirect()->back()->with('error', $aksi === 'submit'
                    ? 'Tidak ada peserta terpilih yang sedang mengerjakan.'
                    : 'Peserta terpilih belum punya attempt.');
            }

            return $aksi === 'submit'
                ? $this->report(MoodleBridge::call('finish_attempts', ['attemptids' => $attempts]), 'paksa_submit', '%d attempt disubmit.')
                : $this->report(MoodleBridge::call('delete_attempts', ['attemptids' => $attempts]), 'hapus_attempt', '%d attempt dihapus.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Aksi gagal: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Bantuan

    private function report(array $items, string $logAction, string $successText)
    {
        $ok     = 0;
        $errors = [];
        foreach ($items as $item) {
            if (isset($item['error'])) {
                $errors[] = $item['error'];
            } else {
                $ok++;
            }
        }
        ActivityLog::add($logAction, $ok . ' berhasil, ' . count($errors) . ' gagal');

        $message = sprintf($successText, $ok);
        if ($errors !== []) {
            $message .= ' Gagal ' . count($errors) . ': ' . implode('; ', array_slice(array_unique($errors), 0, 3));
        }

        return redirect()->back()->with($errors === [] ? 'success' : 'error', $message);
    }

    /** ID attempt terakhir untuk tiap pasangan quiz/user. */
    private function latestAttempts(array $pairs, ?array $states): array
    {
        $builder = Moodle::db()->table(Moodle::t('quiz_attempts'))
            ->select('id, quiz, userid, attempt, state')
            ->where('preview', 0)
            ->groupStart();
        foreach ($pairs as $quizId => $userIds) {
            $builder->orGroupStart()->where('quiz', $quizId)->whereIn('userid', $userIds)->groupEnd();
        }
        $builder->groupEnd()->orderBy('attempt', 'ASC');

        $latest = [];
        foreach ($builder->get()->getResultArray() as $a) {
            $latest[$a['quiz'] . ':' . $a['userid']] = $a;
        }

        $ids = [];
        foreach ($latest as $a) {
            if ($states === null || in_array($a['state'], $states, true)) {
                $ids[] = (int) $a['id'];
            }
        }

        return $ids;
    }
}
