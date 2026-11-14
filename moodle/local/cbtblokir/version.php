<?php
// Plugin CBT: pesan "User Anda terblokir" saat akun yang diblokir (suspended) mencoba login.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_cbtblokir';
$plugin->version   = 2026111400;
$plugin->requires  = 2024100700; // Moodle 4.5+ (hook output).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
