<?php
// Plugin CBT: nama depan (first name) di samping ikon profil di kanan atas.
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_cbtnama';
$plugin->version   = 2026111400;
$plugin->requires  = 2024100700; // Moodle 4.5+ (hook output).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0';
