<?php

namespace App\Controllers;

use App\Libraries\ActiveTest;
use App\Libraries\ActivityLog;
use App\Libraries\Moodle;
use App\Libraries\MoodleBridge;
use App\Libraries\Settings;

/**
 * Status Test: pilih kategori kursus Moodle sebagai tes aktif, atur mata pelajaran
 * yang dibuka, dan kelola token bersama untuk semua quiz di kategori itu.
 */
class StatusTes extends PanelController
{
    public function index(): string
    {
        $categories = [];
        $courses    = [];
        $rows       = [];
        $error      = false;
        $category   = null;  // tes aktif (tersimpan)
        $shown      = null;  // kategori yang mapelnya sedang ditampilkan
        $catId      = ActiveTest::categoryId();
        $pick       = (int) $this->request->getGet('kat');

        try {
            $categories = Moodle::categories();
            foreach ($categories as $c) {
                if ((int) $c['id'] === $catId) {
                    $category = $c;
                }
                if ((int) $c['id'] === $pick) {
                    $shown = $c;
                }
            }
            $shown ??= $category;
            if ($shown) {
                $courses = Moodle::coursesInCategory((int) $shown['id']);
            }
            if ($category) {
                $rows = self::quizRows($catId);
            }
        } catch (\Throwable $e) {
            $error = true;
            log_message('error', 'Status Test: ' . $e->getMessage());
        }

        return $this->render('status/tes', [
            'title'        => 'Status Test',
            'categories'   => $categories,
            'category'     => $category,
            'shown'        => $shown,
            'courses'      => $courses,
            'rows'         => $rows,
            'moodleError'  => $error,
            'tokenEnabled' => ActiveTest::tokenEnabled(),
            'tokenMinutes' => ActiveTest::tokenMinutes(),
            'token'        => ActiveTest::token(),
            'nextRelease'  => ActiveTest::nextReleaseAt(),
        ]);
    }

    /** Token yang berlaku, untuk diperbarui halaman tanpa muat ulang. */
    public function token()
    {
        return $this->response->setJSON([
            'token'   => ActiveTest::token(),
            'enabled' => ActiveTest::tokenEnabled(),
            'next'    => ActiveTest::nextReleaseAt(),
            'now'     => time(),
        ]);
    }

    /**
     * Simpan tes aktif (kategori) beserta mapel yang dibuka, lalu pasang token di quiz-quiznya.
     */
    public function kategori()
    {
        $catId = (int) $this->request->getPost('kat_id');
        $back  = site_url('status_tes') . '?kat=' . $catId;
        $name  = null;
        try {
            foreach (Moodle::categories() as $c) {
                if ((int) $c['id'] === $catId) {
                    $name = $c['name'];
                }
            }
        } catch (\Throwable $e) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Database Moodle tidak bisa dibaca.');
        }
        if ($name === null) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Pilih tes (kategori) terlebih dahulu.');
        }

        // Mapel yang dicentang dibuka, sisanya disembunyikan dari peserta.
        $chosen = array_map('intval', (array) $this->request->getPost('ids'));
        try {
            $changes = [];
            $opened  = [];
            $courses = Moodle::coursesInCategory($catId);
            foreach ($courses as $c) {
                $visible = in_array((int) $c['id'], $chosen, true);
                if ($visible) {
                    $opened[] = $c['shortname'];
                }
                if ((bool) (int) $c['visible'] !== $visible) {
                    $changes[] = ['id' => (int) $c['id'], 'visible' => $visible];
                }
            }
            if ($courses !== [] && $opened === []) {
                return redirect()->to($back)->with('error', 'Harus ada minimal 1 mata pelajaran yang aktif.');
            }
            $failed = $changes === [] ? [] : array_filter(
                MoodleBridge::call('course_visibility', ['courses' => $changes]),
                static fn ($item) => isset($item['error'])
            );
        } catch (\Throwable $e) {
            return redirect()->to($back)->with('error', 'Gagal memperbarui mata pelajaran: ' . $e->getMessage());
        }
        if ($failed !== []) {
            return redirect()->to($back)->with('error', count($failed) . ' mata pelajaran gagal diubah: ' . reset($failed)['error']);
        }

        Settings::set('tes_kategori', (string) $catId);
        $message = 'Tes aktif: ' . $name . ', ' . count($opened) . ' dari ' . count($courses) . ' mapel dibuka.';
        try {
            if (ActiveTest::tokenEnabled()) {
                if (ActiveTest::token() === '') {
                    $message .= ' Token: ' . ActiveTest::release() . '.';
                } else {
                    ActiveTest::sync();
                    $message .= ' Token ' . ActiveTest::token() . ' dipasang di semua quiz.';
                }
            }
        } catch (\Throwable $e) {
            $message .= ' Tetapi token gagal dipasang: ' . $e->getMessage();
        }
        ActivityLog::add('tes_aktif', $name . ' (kategori ' . $catId . '); mapel aktif: ' . implode(', ', $opened));

        return redirect()->to(site_url('status_tes'))->with('success', $message);
    }

    /** Release token baru sekarang. */
    public function release()
    {
        if (! ActiveTest::tokenEnabled()) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Token sedang dinonaktifkan. Aktifkan di Setting → Setting Token.');
        }
        if (ActiveTest::categoryId() === 0) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Pilih dan simpan tes terlebih dahulu.');
        }
        try {
            $token = ActiveTest::release();
        } catch (\Throwable $e) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Token gagal direlease: ' . $e->getMessage());
        }
        ActivityLog::add('release_token', $token);

        return redirect()->to(site_url('status_tes'))->with('success', 'Token direlease: ' . $token);
    }

    /** Pengaturan token: aktif/nonaktif dan jarak release otomatis. */
    public function tokenSetting()
    {
        $wasEnabled = ActiveTest::tokenEnabled();
        $enabled    = (bool) $this->request->getPost('aktif');
        $minutes    = (int) $this->request->getPost('menit');
        if ($minutes < 1 || $minutes > 1440) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Timer token harus 1–1440 menit.');
        }

        Settings::set('token_menit', (string) $minutes);
        Settings::set('token_aktif', $enabled ? '1' : '0');
        $message = 'Pengaturan token disimpan.';
        try {
            if ($wasEnabled && ! $enabled) {
                // Token dimatikan: lepas password dari semua quiz kategori aktif.
                ActiveTest::apply('');
                Settings::set('token_nilai', '');
                $message .= ' Token dinonaktifkan dan dilepas dari semua quiz.';
            } elseif ($enabled && ActiveTest::categoryId() > 0 && ActiveTest::token() === '') {
                $message .= ' Token: ' . ActiveTest::release() . '.';
            }
        } catch (\Throwable $e) {
            $message .= ' Tetapi quiz gagal diperbarui: ' . $e->getMessage();
        }
        ActivityLog::add('setting_token', ($enabled ? 'aktif' : 'nonaktif') . ', ' . $minutes . ' menit');

        return redirect()->to(site_url('status_tes'))->with('success', $message);
    }

    /** Reset Default: buka kembali semua mata pelajaran di kategori aktif (ujian susulan). */
    public function reset()
    {
        $catId = ActiveTest::categoryId();
        if ($catId === 0) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Belum ada tes yang dipilih.');
        }
        try {
            $changes = [];
            foreach (Moodle::coursesInCategory($catId) as $c) {
                if (! (int) $c['visible']) {
                    $changes[] = ['id' => (int) $c['id'], 'visible' => true];
                }
            }
            if ($changes !== []) {
                MoodleBridge::call('course_visibility', ['courses' => $changes]);
            }
        } catch (\Throwable $e) {
            return redirect()->to(site_url('status_tes'))->with('error', 'Reset default gagal: ' . $e->getMessage());
        }
        ActivityLog::add('reset_status_tes', count($changes) . ' mapel dibuka kembali');

        return redirect()->to(site_url('status_tes'))->with('success', 'Status tes dikembalikan: semua mata pelajaran dibuka kembali.');
    }

    /** Buka sekarang, tutup sekarang, atau atur waktu satu quiz. */
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

    /** Quiz di kategori aktif beserta jumlah peserta per status. */
    private static function quizRows(int $catId): array
    {
        $rows = Moodle::quizzes($catId);
        if ($rows === []) {
            return [];
        }
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

        return $rows;
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
        if (! (int) $quiz['visible'] || (isset($quiz['course_visible']) && ! (int) $quiz['course_visible'])) {
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
