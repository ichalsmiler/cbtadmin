<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;
use App\Libraries\QuizStatus;
use App\Libraries\Tingkat;

/**
 * Hapus attempt quiz di Moodle (khusus administrator). Moodle mengunci quiz ke 1 percobaan,
 * jadi menghapus attempt adalah satu-satunya cara memberi siswa kesempatan mengerjakan ulang.
 */
class HapusAttempt extends PanelController
{
    public function index(): string
    {
        $quizId  = (int) $this->request->getGet('quiz');
        $ruangId = (string) $this->request->getGet('ruang');
        $kelas   = (string) $this->request->getGet('kelas');
        $rows    = [];
        $quizzes = [];
        $quiz    = null;
        $error   = false;

        try {
            $quizzes = Moodle::quizzes();
            $quiz    = $quizId > 0 ? QuizStatus::findQuiz($quizId) : null;
            $all     = $quiz ? QuizStatus::forQuiz($quiz) : QuizStatus::allQuizzes();
            // Hanya peserta yang sudah punya attempt (sedang mengerjakan atau selesai).
            $rows = array_values(array_filter(
                QuizStatus::filter($all, $ruangId, $kelas, Tingkat::dariRequest()),
                static fn (array $r): bool => $r['status'] !== 'notstarted'
            ));
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Hapus Attempt: ' . $e->getMessage());
        }

        return $this->render('hapus_attempt/index', [
            'title'     => 'Hapus Attempt',
            'rows'      => $rows,
            'quiz'      => $quiz,
            'quizzes'   => $quizzes,
            'ruangList' => $this->ruangList(),
            'ruangId'   => $ruangId,
            'kelasList' => array_column(db_connect()->query("SELECT DISTINCT kelas FROM peserta WHERE kelas <> '' ORDER BY kelas")->getResultArray(), 'kelas'),
            'kelas'     => $kelas,
            'tingkat'   => Tingkat::dariRequest(),
            'error'     => $error,
        ]);
    }

    public function hapus()
    {
        $pairs = [];
        $who   = [];
        foreach ((array) $this->request->getPost('ids') as $value) {
            if (preg_match('/^(\d+):(\d+)$/', (string) $value, $m)) {
                $pairs[(int) $m[1]][] = (int) $m[2];
            }
        }
        if ($pairs === []) {
            return redirect()->back()->with('error', 'Belum ada peserta yang dicentang.');
        }
        $alasan = trim((string) $this->request->getPost('alasan'));
        if (mb_strlen($alasan) < 3) {
            return redirect()->back()->with('error', 'Tulis alasan menghapus attempt (mis. komputer rusak, salah quiz).');
        }

        try {
            $attempts = QuizStatus::latestAttemptIds($pairs);
            if ($attempts === []) {
                return redirect()->back()->with('error', 'Peserta terpilih belum punya attempt.');
            }

            // Catat siapa dan quiz apa yang dihapus, untuk jejak di Log Aktivitas.
            $info = Moodle::db()->table(Moodle::t('quiz_attempts') . ' qa')
                ->select('u.username, q.name AS quiz')
                ->join(Moodle::t('user') . ' u', 'u.id = qa.userid')
                ->join(Moodle::t('quiz') . ' q', 'q.id = qa.quiz')
                ->whereIn('qa.id', $attempts)
                ->get()->getResultArray();
            foreach ($info as $i) {
                $who[] = $i['username'] . ' (' . $i['quiz'] . ')';
            }

            $items = MoodleBridge::call('delete_attempts', ['attemptids' => $attempts]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }

        $ok     = 0;
        $errors = [];
        foreach ($items as $item) {
            if (isset($item['error'])) {
                $errors[] = $item['error'];
            } else {
                $ok++;
            }
        }
        ActivityLog::add('hapus_attempt', $ok . ' attempt: ' . implode(', ', array_slice($who, 0, 30)) . '. Alasan: ' . $alasan);

        $message = $ok . ' attempt dihapus. Peserta bisa mulai mengerjakan ulang.';
        if ($errors !== []) {
            $message .= ' Gagal ' . count($errors) . ': ' . implode('; ', array_slice(array_unique($errors), 0, 3));
        }

        return redirect()->back()->with($errors === [] ? 'success' : 'error', $message);
    }
}
