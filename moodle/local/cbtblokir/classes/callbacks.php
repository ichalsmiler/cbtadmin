<?php
namespace local_cbtblokir;

use core\event\user_login_failed;
use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Moodle memberi pesan umum "Login tidak valid" untuk akun yang disuspend. Plugin ini
 * mencatat bahwa login gagal karena akun diblokir, lalu mengganti pesan itu di halaman login.
 */
class callbacks {
    /** Login gagal: tandai di sesi bila penyebabnya akun diblokir (suspended). */
    public static function login_gagal(user_login_failed $event): void {
        global $CFG, $SESSION;
        require_once($CFG->libdir . '/authlib.php');

        if ((int) ($event->other['reason'] ?? 0) === AUTH_LOGIN_SUSPENDED) {
            $SESSION->local_cbtblokir = true;
        }
    }

    /** Halaman login: ganti pesan error dengan "User Anda terblokir". */
    public static function halaman_login(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $SESSION;

        if ($PAGE->pagetype !== 'login-index' || empty($SESSION->local_cbtblokir)) {
            return;
        }
        unset($SESSION->local_cbtblokir);

        $pesan = json_encode(get_string('diblokir', 'local_cbtblokir'));
        $hook->add_html(\html_writer::script(
            "document.addEventListener('DOMContentLoaded',function(){var e=document.getElementById('loginerrormessage');"
            . "if(e){e.textContent={$pesan};}});"
        ));
    }
}
