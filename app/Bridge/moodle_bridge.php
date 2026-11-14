<?php
/**
 * Jembatan ke Moodle: dijalankan lewat CLI oleh panel (App\Libraries\MoodleBridge)
 * supaya perubahan data memakai fungsi Moodle sendiri, bukan SQL langsung.
 *
 * Masukan : JSON di STDIN  {"config": "/path/config.php", "action": "...", ...}
 * Keluaran: baris "@@CBTJSON@@" diikuti JSON hasil.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$in = json_decode((string) stream_get_contents(STDIN), true);
if (! is_array($in) || empty($in['action']) || empty($in['config']) || ! is_file($in['config'])) {
    echo "\n@@CBTJSON@@" . json_encode(['ok' => false, 'error' => 'Masukan jembatan tidak valid.']);
    exit(1);
}

define('CLI_SCRIPT', true);
require $in['config'];
require_once $CFG->dirroot . '/user/lib.php';
require_once $CFG->dirroot . '/cohort/lib.php';
require_once $CFG->dirroot . '/mod/quiz/lib.php';
require_once $CFG->dirroot . '/mod/quiz/locallib.php';

// Semua perubahan dicatat di log Moodle atas nama admin situs.
\core\session\manager::set_user(get_admin());

/** Cari atau buat cohort tingkat situs dengan nama tertentu, lalu masukkan user. */
function cbt_add_to_cohort(int $userid, string $name): void
{
    global $DB;
    $context = context_system::instance();
    $cohort  = $DB->get_record('cohort', ['name' => $name, 'contextid' => $context->id], '*', IGNORE_MULTIPLE);
    if ($cohort) {
        $cohortid = (int) $cohort->id;
    } else {
        $cohortid = cohort_add_cohort((object) [
            'contextid'         => $context->id,
            'name'              => $name,
            'idnumber'          => '',
            'description'       => '',
            'descriptionformat' => FORMAT_HTML,
            'visible'           => 1,
            'component'         => '',
        ]);
    }
    if (! cohort_is_member($cohortid, $userid)) {
        cohort_add_member($cohortid, $userid);
    }
}

/** Tolak password yang melanggar kebijakan password situs Moodle. */
function cbt_check_password(string $password, ?stdClass $user = null): void
{
    $error = '';
    if (! check_password_policy($password, $error, $user)) {
        throw new RuntimeException($error);
    }
}

/** Pesan error Moodle berisi HTML; jadikan satu baris teks biasa. */
function cbt_clean_error(string $message): string
{
    $text = html_entity_decode(strip_tags(str_replace('</div>', ' ', $message)), ENT_QUOTES);

    return trim(preg_replace('/\s+/', ' ', preg_replace('#^error/#', '', trim($text))));
}

$result = ['ok' => true, 'items' => []];

try {
    switch ($in['action']) {
        case 'create_users':
            foreach ($in['users'] as $u) {
                $username = core_text::strtolower(trim((string) $u['username']));
                $item     = ['username' => $username];
                try {
                    $existing = $DB->get_record('user', [
                        'username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0,
                    ]);
                    if ($existing) {
                        if (! empty($u['password'])) {
                            cbt_check_password((string) $u['password'], $existing);
                            update_internal_user_password($existing, (string) $u['password']);
                        }
                        $item += ['id' => (int) $existing->id, 'status' => 'diperbarui'];
                    } else {
                        $user = (object) [
                            'auth'       => 'manual',
                            'confirmed'  => 1,
                            'mnethostid' => $CFG->mnet_localhost_id,
                            'username'   => $username,
                            'password'   => (string) $u['password'],
                            'firstname'  => (string) $u['firstname'],
                            'lastname'   => (string) $u['lastname'],
                            'email'      => (string) $u['email'],
                            'lang'       => $CFG->lang,
                        ];
                        $item += ['id' => (int) user_create_user($user, true, false), 'status' => 'dibuat'];
                    }
                    if (! empty($u['cohort'])) {
                        cbt_add_to_cohort($item['id'], (string) $u['cohort']);
                    }
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'reset_passwords':
            foreach ($in['users'] as $u) {
                $item = ['id' => (int) $u['id']];
                try {
                    $user = $DB->get_record('user', ['id' => (int) $u['id'], 'deleted' => 0], '*', MUST_EXIST);
                    if (is_siteadmin($user) || isguestuser($user)) {
                        throw new RuntimeException('Akun admin tidak boleh diubah dari panel.');
                    }
                    cbt_check_password((string) $u['password'], $user);
                    update_internal_user_password($user, (string) $u['password']);
                    $item['status'] = 'direset';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'quiz_times':
            // Ubah waktu buka/tutup quiz, lalu perbarui kalender, cache kursus, dan attempt yang sedang berjalan.
            $quiz            = $DB->get_record('quiz', ['id' => (int) $in['quizid']], '*', MUST_EXIST);
            $quiz->timeopen  = max(0, (int) $in['timeopen']);
            $quiz->timeclose = max(0, (int) $in['timeclose']);
            if ($quiz->timeopen && $quiz->timeclose && $quiz->timeclose <= $quiz->timeopen) {
                throw new RuntimeException('Waktu tutup harus setelah waktu buka.');
            }
            $quiz->timemodified = time();
            $DB->update_record('quiz', $quiz);
            quiz_update_events($quiz);
            rebuild_course_cache((int) $quiz->course, true);
            quiz_update_open_attempts(['quizid' => (int) $quiz->id]);
            $result['items'][] = ['id' => (int) $quiz->id, 'status' => 'diubah'];
            break;

        case 'finish_attempts':
            foreach ($in['attemptids'] as $attemptid) {
                $item = ['id' => (int) $attemptid];
                try {
                    $attemptobj = \mod_quiz\quiz_attempt::create((int) $attemptid);
                    $state      = $attemptobj->get_attempt()->state;
                    if (! in_array($state, [\mod_quiz\quiz_attempt::IN_PROGRESS, \mod_quiz\quiz_attempt::OVERDUE], true)) {
                        throw new RuntimeException('Attempt ini sudah tidak berjalan.');
                    }
                    $attemptobj->process_finish(time(), false);
                    $item['status'] = 'disubmit';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'delete_attempts':
            foreach ($in['attemptids'] as $attemptid) {
                $item = ['id' => (int) $attemptid];
                try {
                    $attempt = $DB->get_record('quiz_attempts', ['id' => (int) $attemptid], '*', MUST_EXIST);
                    $quiz    = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
                    quiz_delete_attempt($attempt, $quiz);
                    $item['status'] = 'dihapus';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'add_time':
            // Tambah waktu lewat override per user: batas waktu dan waktu tutup sama-sama diperpanjang.
            $quizobj = \mod_quiz\quiz_settings::create((int) $in['quizid']);
            $quiz    = $quizobj->get_quiz();
            $manager = $quizobj->get_override_manager();
            $extra   = max(1, (int) $in['minutes']) * 60;
            foreach ($in['userids'] as $userid) {
                $item = ['userid' => (int) $userid];
                try {
                    $existing = $DB->get_record('quiz_overrides', ['quiz' => $quiz->id, 'userid' => (int) $userid]);
                    $limit    = (int) ($existing->timelimit ?? 0) ?: (int) $quiz->timelimit;
                    $close    = (int) ($existing->timeclose ?? 0) ?: (int) $quiz->timeclose;
                    if ($limit === 0 && $close === 0) {
                        throw new RuntimeException('Quiz ini tidak punya batas waktu maupun waktu tutup.');
                    }
                    $data = ['userid' => (int) $userid];
                    if ($existing) {
                        $data['id'] = (int) $existing->id;
                        foreach (['timeopen', 'attempts', 'password'] as $keep) {
                            if ($existing->$keep !== null) {
                                $data[$keep] = $existing->$keep;
                            }
                        }
                    }
                    if ($limit > 0) {
                        $data['timelimit'] = $limit + $extra;
                    }
                    if ($close > 0) {
                        $data['timeclose'] = max($close, time()) + $extra;
                    }
                    $manager->save_override($data);
                    $item += ['status' => 'ditambah', 'timelimit' => $data['timelimit'] ?? 0, 'timeclose' => $data['timeclose'] ?? 0];
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'list_sessions':
            // Sesi login aktif per user (handler sesi apa pun, termasuk Redis).
            foreach ($in['userids'] as $userid) {
                $sessions = \core\session\manager::get_sessions_by_userid((int) $userid);
                if ($sessions === []) {
                    continue;
                }
                usort($sessions, static fn ($a, $b) => (int) $b->timemodified <=> (int) $a->timemodified);
                $result['items'][] = [
                    'userid'       => (int) $userid,
                    'count'        => count($sessions),
                    'lastip'       => (string) $sessions[0]->lastip,
                    'timecreated'  => (int) $sessions[0]->timecreated,
                    'timemodified' => (int) $sessions[0]->timemodified,
                ];
            }
            break;

        case 'kill_sessions':
            foreach ($in['userids'] as $userid) {
                $item = ['userid' => (int) $userid];
                try {
                    $user = $DB->get_record('user', ['id' => (int) $userid, 'deleted' => 0], '*', MUST_EXIST);
                    if (is_siteadmin($user)) {
                        throw new RuntimeException('Sesi admin situs tidak direset dari panel.');
                    }
                    $item['count'] = count(\core\session\manager::get_sessions_by_userid((int) $userid));
                    \core\session\manager::destroy_user_sessions((int) $userid);
                    $item['status'] = 'direset';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'quiz_password':
            // Pasang password (token) yang sama di banyak quiz sekaligus. Password kosong = tanpa token.
            $password = (string) $in['password'];
            $courses  = [];
            foreach ($in['quizids'] as $quizid) {
                $item = ['id' => (int) $quizid];
                try {
                    $quiz = $DB->get_record('quiz', ['id' => (int) $quizid], 'id, course', MUST_EXIST);
                    $DB->update_record('quiz', (object) ['id' => $quiz->id, 'password' => $password, 'timemodified' => time()]);
                    $courses[(int) $quiz->course] = true;
                    $item['status'] = 'diubah';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            foreach (array_keys($courses) as $courseid) {
                rebuild_course_cache($courseid, true);
            }
            break;

        case 'course_visibility':
            // Tampilkan atau sembunyikan kursus (mata pelajaran) lewat fungsi Moodle.
            require_once $CFG->dirroot . '/course/lib.php';
            foreach ($in['courses'] as $c) {
                $item = ['id' => (int) $c['id']];
                try {
                    if ((int) $c['id'] <= 1) {
                        throw new RuntimeException('Halaman depan situs tidak bisa diubah.');
                    }
                    course_change_visibility((int) $c['id'], ! empty($c['visible']));
                    $item['status'] = empty($c['visible']) ? 'disembunyikan' : 'ditampilkan';
                } catch (Throwable $e) {
                    $item['error'] = cbt_clean_error($e->getMessage());
                }
                $result['items'][] = $item;
            }
            break;

        case 'session_gc':
            // Hapus sesi Moodle yang sudah melewati batas waktu sesi situs.
            \core\session\manager::gc();
            $result['items'][] = ['status' => 'dibersihkan', 'timeout' => (int) $CFG->sessiontimeout];
            break;

        default:
            $result = ['ok' => false, 'error' => 'Aksi tidak dikenal: ' . $in['action']];
    }
} catch (Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}

echo "\n@@CBTJSON@@" . json_encode($result);
