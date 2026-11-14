<?php
// Plugin CBT: nama dan kelas siswa di bawah judul halaman Daftar Ujian.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_cbtidentitas';
$plugin->version   = 2026111400;
$plugin->requires  = 2024100700; // Moodle 4.5+ (hook output).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
