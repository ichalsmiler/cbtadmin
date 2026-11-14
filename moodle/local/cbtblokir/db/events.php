<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\user_login_failed',
        'callback'  => '\local_cbtblokir\callbacks::login_gagal',
    ],
];
