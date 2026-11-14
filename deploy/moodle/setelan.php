<?php
/**
 * Setelan Moodle untuk ujian CBT. Dijalankan oleh deploy/moodle/pasang.sh sebagai www-data:
 *   php setelan.php /var/www/moodle/config.php
 * Aman dijalankan berulang kali: hanya nilai yang berbeda yang diubah, dan setiap perubahan dicetak.
 * Untuk membatalkan satu setelan, ubah lewat Site administration seperti biasa
 * (menjalankan skrip ini lagi akan memasangnya kembali).
 */
define('CLI_SCRIPT', true);

$config = $argv[1] ?? '/var/www/moodle/config.php';
require $config;
require_once $CFG->libdir . '/adminlib.php';
require_once $CFG->libdir . '/accesslib.php';

global $DB;
$dir     = __DIR__;
$ubah    = 0;
$catatan = static function (string $teks): void { echo "  - $teks\n"; };

/** Pasang satu setelan bila nilainya berbeda. */
$setel = static function (string $name, $value, ?string $plugin = null) use (&$ubah, $catatan): void {
    $lama = get_config($plugin ?? 'core', $name);
    if ((string) $lama !== (string) $value) {
        set_config($name, $value, $plugin);
        $catatan(($plugin ? "$plugin/" : '') . "$name: " . var_export($lama, true) . ' -> ' . var_export((string) $value, true));
        $ubah++;
    }
};

\core\session\manager::set_user(get_admin());

// ---------------------------------------------------------------------- Bahasa Indonesia
echo "== Bahasa\n";
$sm = get_string_manager();
if (! $sm->translation_exists('id', false)) {
    try {
        $c = new \tool_langimport\controller();
        $c->install_languagepacks(['id']);
        $sm->reset_caches();
        $catatan('paket bahasa Indonesia dipasang');
    } catch (\Throwable $e) {
        echo "  [PERINGATAN] Paket bahasa Indonesia gagal diunduh (perlu internet): {$e->getMessage()}\n";
    }
}
if ($sm->translation_exists('id', false)) {
    $setel('lang', 'id');
    // Akun yang masih berbahasa Inggris ikut Bahasa Indonesia (kecuali admin situs).
    $admins = array_filter(array_map('intval', explode(',', (string) $CFG->siteadmins)));
    [$notin, $params] = $DB->get_in_or_equal($admins ?: [0], SQL_PARAMS_NAMED, 'a', false);
    $n = $DB->count_records_select('user', "deleted = 0 AND lang = 'en' AND id $notin", $params);
    if ($n > 0) {
        $DB->execute("UPDATE {user} SET lang = 'id' WHERE deleted = 0 AND lang = 'en' AND id $notin", $params);
        $catatan("$n akun diubah ke Bahasa Indonesia");
    }
}
$setel('langmenu', 0);          // siswa tidak bisa mengganti bahasa
$setel('country', 'ID');
$setel('timezone', 'Asia/Makassar');
$setel('forcetimezone', 'Asia/Makassar');

// Teks kustom: Daftar Ujian, sapaan nama - kelas, pesan sesi berakhir.
foreach (['id_local', 'en_local'] as $lokal) {
    $tujuan = $CFG->dataroot . '/lang/' . $lokal;
    if (! is_dir($tujuan)) {
        mkdir($tujuan, $CFG->directorypermissions, true);
    }
    foreach (glob($dir . '/lang/*.php') as $sumber) {
        $file = $tujuan . '/' . basename($sumber);
        if (! is_file($file) || file_get_contents($file) !== file_get_contents($sumber)) {
            copy($sumber, $file);
            $catatan("teks kustom lang/$lokal/" . basename($sumber));
            $ubah++;
        }
    }
}

// ---------------------------------------------------------------------- Login dan keamanan
echo "== Login dan keamanan\n";
$setel('forcelogin', 1);                 // wajib login
$setel('guestloginbutton', 0);           // tanpa tombol masuk sebagai tamu
$setel('auth', implode(',', array_diff(explode(',', (string) $CFG->auth), ['email', ''])));  // tanpa daftar mandiri
$setel('registerauth', '');
$setel('limitconcurrentlogins', 1);      // satu akun satu perangkat
$setel('passwordpolicy', 0);             // Upload users tidak memaksa ganti kata sandi
$setel('debug', 0);
$setel('debugdisplay', 0);

// Siswa (Authenticated user): tidak bisa mengubah profil/kata sandi dan tidak bisa mengirim pesan.
$sys  = context_system::instance();
$user = $DB->get_record('role', ['shortname' => 'user'], '*', MUST_EXIST);
foreach (['moodle/user:editownprofile', 'moodle/user:changeownpassword', 'moodle/user:editownmessageprofile',
          'moodle/user:manageownfiles', 'moodle/site:sendmessage'] as $cap) {
    if ((int) $DB->get_field('role_capabilities', 'permission', ['roleid' => $user->id, 'capability' => $cap, 'contextid' => $sys->id]) !== CAP_PREVENT) {
        assign_capability($cap, CAP_PREVENT, $user->id, $sys->id, true);
        $catatan("Authenticated user: $cap -> Prevent");
        $ubah++;
    }
}

// ---------------------------------------------------------------------- Tampilan siswa
echo "== Tampilan\n";
$setel('defaulthomepage', 3);            // setelah login langsung ke Daftar Ujian (Kursusku)
$setel('allowguestmymoodle', 0);
$setel('enabledashboard', 0);            // tanpa Dasbor
$setel('messaging', 0);                  // tanpa pesan
$setel('enableblogs', 0);
$setel('usecomments', 0);
$setel('enablenotes', 0);
$setel('enablebadges', 0);
$setel('customusermenuitems', 'mycourses,moodle|/my/courses.php');   // menu profil: Daftar Ujian + Keluar
foreach (['inprogress', 'future', 'past', 'favourites'] as $g) {
    $setel('displaygrouping' . $g, 0, 'block_myoverview');           // Daftar Ujian: semua mapel tanpa filter
}
$setel('displaygroupingall', 1, 'block_myoverview');

foreach (['online_users', 'calendar_month', 'calendar_upcoming', 'timeline'] as $blok) {
    if ($DB->get_field('block', 'visible', ['name' => $blok])) {
        $DB->set_field('block', 'visible', 0, ['name' => $blok]);
        $catatan("blok $blok disembunyikan");
        $ubah++;
    }
}
foreach ($DB->get_records('tool_usertours_tours', ['enabled' => 1]) as $t) {
    $tour = \tool_usertours\tour::load_from_record($t);
    $tour->set_enabled(false);
    $tour->persist();
    $catatan("tour dimatikan: {$t->name}");
    $ubah++;
}

// CSS tema Boost: blok CBT diganti dari deploy/moodle/boost.scss; SCSS lain di luar blok dibiarkan.
$mulai = '/* === CBT MULAI (diatur deploy/setelan-moodle.sh) === */';
$akhir = '/* === CBT SELESAI === */';
$scss  = (string) get_config('theme_boost', 'scss');
$blokCss = $mulai . "\n" . trim((string) file_get_contents($dir . '/boost.scss')) . "\n" . $akhir;
if (str_contains($scss, $mulai)) {
    $baru = preg_replace('/' . preg_quote($mulai, '/') . '.*?' . preg_quote($akhir, '/') . '/s', addcslashes($blokCss, '\\$'), $scss);
} elseif (str_contains($scss, '/* CBT:')) {
    $baru = $blokCss;   // CSS CBT lama tanpa penanda: diganti seluruhnya
} else {
    $baru = trim($scss . "\n\n" . $blokCss);
}
if (trim($baru) !== trim($scss)) {
    set_config('scss', $baru, 'theme_boost');
    $catatan('CSS tema Boost diperbarui');
    $ubah++;
}

// ---------------------------------------------------------------------- Course dan quiz
echo "== Course dan quiz\n";
$setel('format', 'singleactivity', 'moodlecourse');      // course baru: Single activity
$setel('activitytype', 'quiz', 'format_singleactivity'); // ... berisi Quiz
$setel('courseenddateenabled', 0, 'moodlecourse');       // tanpa tanggal selesai course
$setel('attempts', 1, 'quiz');                           // 1 kali percobaan,
$setel('attempts_locked', 1, 'quiz');                    // dikunci di form quiz
$setel('timelimit', 5400, 'quiz');
$setel('overduehandling', 'autosubmit', 'quiz');         // tersubmit otomatis saat waktu habis
$setel('autosaveperiod', 60, 'quiz');                    // jawaban tersimpan tiap 60 detik
$setel('questionsperpage', 1, 'quiz');
$setel('navmethod', 'free', 'quiz');
$setel('shuffleanswers', 1, 'quiz');
$setel('preferredbehaviour', 'deferredfeedback', 'quiz');
// Tinjauan: tanpa kunci jawaban dan benar/salah; nilai baru tampil setelah quiz ditutup.
$setel('reviewattempt', 0x10000, 'quiz');
$setel('reviewcorrectness', 0, 'quiz');
$setel('reviewmarks', 0x10, 'quiz');
$setel('reviewspecificfeedback', 0, 'quiz');
$setel('reviewgeneralfeedback', 0, 'quiz');
$setel('reviewrightanswer', 0, 'quiz');
$setel('reviewoverallfeedback', 0, 'quiz');

// Guru/manager tidak bisa memberi kesempatan kedua (override percobaan, hapus attempt); admin tetap bisa.
foreach (['editingteacher', 'manager'] as $short) {
    $role = $DB->get_record('role', ['shortname' => $short]);
    foreach (['mod/quiz:manageoverrides', 'mod/quiz:deleteattempts'] as $cap) {
        if ($role && $DB->record_exists('role_capabilities', ['roleid' => $role->id, 'capability' => $cap, 'contextid' => $sys->id])) {
            unassign_capability($cap, $role->id, $sys->id);
            $catatan("$short: $cap dicabut");
            $ubah++;
        }
    }
}

// ---------------------------------------------------------------------- Selesai
if ($ubah > 0) {
    theme_reset_all_caches();
    purge_all_caches();
}
echo $ubah > 0 ? "Selesai: $ubah setelan diubah.\n" : "Selesai: semua setelan sudah sesuai, tidak ada yang diubah.\n";
