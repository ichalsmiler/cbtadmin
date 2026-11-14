<?php
namespace local_cbtujian;

use core\event\user_loggedin;
use core\hook\after_config;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Aturan ujian CBT. Setelan (plugin local_cbtujian) diatur dari CBT Admin → Tools:
 *  - antisubmit  : menit sebelum waktu habis saat tombol selesai mulai aktif (0 = mati)
 *  - locklogin   : tolak login bila akun masih login di perangkat lain (pengawas me-reset login)
 *  - resumetimer : waktu selama siswa keluar ditambahkan kembali saat ia login lagi
 *  - antinewtab  : siswa otomatis keluar bila membuka tab/aplikasi lain saat mengerjakan quiz
 */
class callbacks {
    /** Ambang jeda (detik) sebelum Resume timer menambah waktu; autosave quiz tiap 60 detik. */
    private const JEDA_MINIMAL = 120;

    // ------------------------------------------------------------------ Login

    /** Setelah login: Lock login dan Resume timer. */
    public static function login(user_loggedin $event): void {
        global $SESSION;

        $userid = (int) $event->objectid;
        if (is_siteadmin($userid)) {
            return;
        }
        if (get_config('local_cbtujian', 'locklogin') && self::sesi_lain($userid)) {
            // Sesi baru ditolak pada permintaan berikutnya (lihat sebelum_header), supaya alur login Moodle
            // selesai dulu dengan rapi. Sesi lama tetap berjalan.
            $SESSION->local_cbtujian_tolak = true;

            return;
        }
        if (get_config('local_cbtujian', 'resumetimer')) {
            try {
                self::lanjutkan_waktu($userid);
            } catch (\Throwable $e) {
                debugging('local_cbtujian resume timer: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /** Apakah user masih punya sesi aktif lain (selain sesi ini). */
    private static function sesi_lain(int $userid): bool {
        global $CFG;

        $batas = time() - (int) $CFG->sessiontimeout;
        foreach (\core\session\manager::get_sessions_by_userid($userid) as $s) {
            if ($s->sid !== session_id() && (int) $s->timemodified > $batas) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resume timer: untuk setiap attempt yang masih berjalan dan belum habis waktunya, waktu sejak
     * penyimpanan terakhir ditambahkan ke batas waktu siswa itu (override quiz per siswa).
     */
    private static function lanjutkan_waktu(int $userid): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempts = $DB->get_records('quiz_attempts', ['userid' => $userid, 'state' => 'inprogress', 'preview' => 0]);
        foreach ($attempts as $attempt) {
            $jeda = time() - (int) $attempt->timemodified;
            if ($jeda < self::JEDA_MINIMAL) {
                continue;
            }
            $quizobj = \mod_quiz\quiz_settings::create((int) $attempt->quiz, $userid);
            $quiz    = $quizobj->get_quiz();      // sudah termasuk override siswa ini
            if (empty($quiz->timelimit)) {
                continue;
            }
            $batas = (int) $attempt->timestart + (int) $quiz->timelimit;
            if (!empty($quiz->timeclose)) {
                $batas = min($batas, (int) $quiz->timeclose);
            }
            if ($batas <= time()) {
                continue;  // waktu sudah habis; attempt diselesaikan seperti biasa
            }

            $data     = ['userid' => $userid, 'timelimit' => (int) $quiz->timelimit + $jeda];
            $existing = $DB->get_record('quiz_overrides', ['quiz' => $attempt->quiz, 'userid' => $userid]);
            if ($existing) {
                $data['id'] = (int) $existing->id;
                foreach (['timeopen', 'attempts', 'password'] as $tetap) {
                    if ($existing->$tetap !== null) {
                        $data[$tetap] = $existing->$tetap;
                    }
                }
            }
            if (!empty($quiz->timeclose)) {
                $data['timeclose'] = (int) $quiz->timeclose + $jeda;
            }
            $quizobj->get_override_manager()->save_override($data);
            // Jeda sudah diganti; login berikutnya menghitung dari sekarang, bukan menambah jeda yang sama lagi.
            $DB->set_field('quiz_attempts', 'timemodified', time(), ['id' => $attempt->id]);
        }
    }

    // ------------------------------------------------------------------ Awal setiap permintaan (sebelum halaman dibuat)

    public static function setelah_config(after_config $hook): void {
        global $SCRIPT, $SESSION;

        if (CLI_SCRIPT || during_initial_install() || empty($SESSION)) {
            return;
        }

        // Lock login: sesi baru ditolak, kembali ke halaman login dengan pesan.
        if (!empty($SESSION->local_cbtujian_tolak)) {
            unset($SESSION->local_cbtujian_tolak);
            if (isloggedin() && !isguestuser() && !is_siteadmin()) {
                require_logout();
                $SESSION->loginerrormsg = get_string('masihlogin', 'local_cbtujian');
                redirect(get_login_url());
            }
        }

        if ($SCRIPT === '/mod/quiz/summary.php' && isloggedin()) {
            self::anti_submit();
        }
    }

    /** Anti Submit: halaman ringkasan (tombol "Submit all and finish") baru bisa dibuka menjelang waktu habis. */
    private static function anti_submit(): void {
        global $CFG, $USER;

        $menit = (int) get_config('local_cbtujian', 'antisubmit');
        $id    = optional_param('attempt', 0, PARAM_INT);
        if ($menit <= 0 || !$id) {
            return;
        }
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $attempt = \mod_quiz\quiz_attempt::create($id);
        if ((int) $attempt->get_userid() !== (int) $USER->id || $attempt->get_state() !== \mod_quiz\quiz_attempt::IN_PROGRESS) {
            return;
        }
        $due = $attempt->get_due_date();
        if (!$due) {
            return;  // quiz tanpa batas waktu
        }
        $sisa = (int) $due - time();
        if ($sisa > $menit * 60) {
            redirect(
                $attempt->attempt_url(null, $attempt->get_currentpage()),
                get_string('belumbisasubmit', 'local_cbtujian', (object) ['menit' => $menit, 'lagi' => (int) ceil(($sisa - $menit * 60) / 60)]),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    // ------------------------------------------------------------------ Awal halaman

    public static function awal_halaman(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        // Pesan setelah keluar otomatis oleh Anti Newtab.
        if ($PAGE->pagetype === 'login-index') {
            $pesan = json_encode(get_string('antinewtab', 'local_cbtujian'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $hook->add_html(\html_writer::script(
                "document.addEventListener('DOMContentLoaded',function(){try{if(!sessionStorage.getItem('cbtNewtab'))return;"
                . "sessionStorage.removeItem('cbtNewtab');}catch(e){return;}var f=document.querySelector('.login-form');if(!f)return;"
                . "var d=document.createElement('div');d.className='alert alert-danger';d.setAttribute('role','alert');d.textContent={$pesan};"
                . "f.parentNode.insertBefore(d,f);});"
            ));

            return;
        }

        // Anti Newtab: hanya saat mengerjakan quiz.
        if (!get_config('local_cbtujian', 'antinewtab') || !in_array($PAGE->pagetype, ['mod-quiz-attempt', 'mod-quiz-summary'], true)
                || !isloggedin() || is_siteadmin()) {
            return;
        }
        $logout = json_encode((new \moodle_url('/login/logout.php', ['sesskey' => sesskey()]))->out(false));
        $hook->add_html(\html_writer::script(
            "(function(){var keluar=false;function out(){if(keluar)return;keluar=true;try{sessionStorage.setItem('cbtNewtab','1');}catch(e){}"
            . "window.location.href={$logout};}"
            . "document.addEventListener('visibilitychange',function(){if(document.hidden)out();});"
            . "window.addEventListener('blur',function(){setTimeout(function(){if(!document.hasFocus())out();},1500);});})();"
        ));
    }
}
