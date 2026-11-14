<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;

class StatusTes extends PanelController
{
    public function index(): string
    {
        $rows  = [];
        $error = false;

        try {
            $rows = Moodle::quizzes();
            if ($rows !== []) {
                $a      = Moodle::t('quiz_attempts');
                $counts = Moodle::db()->query(
                    "SELECT quiz,
                            COUNT(DISTINCT userid) AS mulai,
                            COUNT(DISTINCT CASE WHEN state IN ('inprogress', 'overdue') THEN userid END) AS sedang,
                            COUNT(DISTINCT CASE WHEN state = 'finished' THEN userid END) AS selesai
                       FROM {$a} WHERE preview = 0 GROUP BY quiz"
                )->getResultArray();
                $counts = array_column($counts, null, 'quiz');
                foreach ($rows as &$row) {
                    $row += $counts[$row['id']] ?? ['mulai' => 0, 'sedang' => 0, 'selesai' => 0];
                    $row['status'] = self::status($row);
                }
                unset($row);
            }
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Status Test: ' . $e->getMessage());
        }

        return $this->render('status/tes', [
            'title'       => 'Status Test',
            'rows'        => $rows,
            'moodleError' => $error,
        ]);
    }

    public function waktu()
    {
        $quizId = (int) $this->request->getPost('quiz');
        $quiz   = null;
        foreach (Moodle::quizzes() as $q) {
            if ((int) $q['id'] === $quizId) {
                $quiz = $q;
            }
        }
        if (! $quiz) {
            return redirect()->back()->with('error', 'Quiz tidak ditemukan.');
        }

        $now   = time();
        $open  = (int) $quiz['timeopen'];
        $close = (int) $quiz['timeclose'];

        switch ($this->request->getPost('aksi')) {
            case 'buka':
                $open = $now;
                if ($close > 0 && $close <= $now) {
                    $close = 0;
                }
                $label = 'dibuka sekarang';
                break;

            case 'tutup':
                $close = $now;
                if ($open >= $now) {
                    $open = 0;
                }
                $label = 'ditutup sekarang';
                break;

            default:
                $open  = self::parseTime((string) $this->request->getPost('timeopen'));
                $close = self::parseTime((string) $this->request->getPost('timeclose'));
                if ($open === false || $close === false) {
                    return redirect()->back()->with('error', 'Format waktu tidak valid.');
                }
                if ($open > 0 && $close > 0 && $close <= $open) {
                    return redirect()->back()->with('error', 'Waktu tutup harus setelah waktu buka.');
                }
                $label = 'diatur: buka ' . ($open ? date('d/m/Y H:i', $open) : 'kapan saja')
                    . ', tutup ' . ($close ? date('d/m/Y H:i', $close) : 'tidak ada');
        }

        try {
            MoodleBridge::call('quiz_times', ['quizid' => $quizId, 'timeopen' => $open, 'timeclose' => $close]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal mengubah waktu quiz: ' . $e->getMessage());
        }
        ActivityLog::add('waktu_quiz', $quiz['name'] . ' ' . $label);

        return redirect()->to(site_url('status_tes'))->with('success', 'Quiz "' . $quiz['name'] . '" ' . $label . '.');
    }

    /** @return int|false 0 untuk kosong, false untuk format salah */
    private static function parseTime(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $ts = strtotime($value);

        return $ts === false ? false : $ts;
    }

    /** buka | belum | tutup | sembunyi */
    public static function status(array $quiz): string
    {
        $now = time();
        if (! (int) $quiz['visible']) {
            return 'sembunyi';
        }
        if ((int) $quiz['timeclose'] > 0 && (int) $quiz['timeclose'] <= $now) {
            return 'tutup';
        }
        if ((int) $quiz['timeopen'] > $now) {
            return 'belum';
        }

        return 'buka';
    }
}
