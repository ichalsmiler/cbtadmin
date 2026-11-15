<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook'     => \core\hook\after_config::class,
        'callback' => [\local_cbtujian\callbacks::class, 'setelah_config'],
    ],
    [
        'hook'     => \core\hook\output\before_http_headers::class,
        'callback' => [\local_cbtujian\callbacks::class, 'sebelum_header'],
    ],
    [
        'hook'     => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => [\local_cbtujian\callbacks::class, 'awal_halaman'],
    ],
];
