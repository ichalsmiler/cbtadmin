<?php
namespace local_cbtidentitas;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Menampilkan nama dan kelas siswa di bawah judul "Daftar Ujian" (halaman Kursusku),
 * supaya siswa yakin sedang memakai akunnya sendiri.
 */
class hook_callbacks {
    public static function identitas(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE, $USER;

        if (!isloggedin() || isguestuser() || $PAGE->pagelayout !== 'mycourses') {
            return;
        }
        // Kelas disimpan di field department; bila kosong, pakai nama belakang (format impor CBT).
        // Format akun CBT: first name = nama siswa, last name = kelas.
        $nama  = trim((string) $USER->firstname);
        $kelas = trim((string) $USER->lastname);

        $html = \html_writer::div(
            \html_writer::div(s($nama), 'h4 mb-0 fw-bold') .
            ($kelas !== '' ? \html_writer::div(s(get_string('kelas', 'local_cbtidentitas', $kelas)), 'text-muted') : ''),
            'cbt-identitas mb-3',
            ['id' => 'cbt-identitas', 'hidden' => 'hidden']
        );
        // Pindahkan ke bawah judul halaman setelah halaman dimuat.
        $js = "document.addEventListener('DOMContentLoaded',function(){var b=document.getElementById('cbt-identitas'),"
            . "h=document.querySelector('#page-header h1, #page-header .page-header-headings');"
            . "if(b&&h){(h.closest('.page-header-headings')||h).after(b);b.hidden=false;}});";

        $hook->add_html($html . \html_writer::script($js));
    }
}
