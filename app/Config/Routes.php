<?php

use App\Filters\Guard;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => Guard::class], static function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('dashboard/stats', 'Dashboard::stats');

    $routes->get('login', 'Auth::login');
    $routes->post('login', 'Auth::attempt');
    $routes->get('logout', 'Auth::logout');

    $routes->get('tools', 'Tools::index');
    $routes->post('tools', 'Tools::save');
    $routes->get('tools/logo', 'Tools::logo');
    $routes->post('tools/akun/simpan', 'Tools::simpanAkun');
    $routes->post('tools/akun/hapus/(:num)', 'Tools::hapusAkun/$1');
    $routes->get('tools/backup', 'Tools::backup');
    $routes->post('tools/bersihkan_sesi', 'Tools::bersihkanSesi');

    $routes->get('pengawas', 'PengawasController::index');
    $routes->post('pengawas/generate', 'PengawasController::generate');
    $routes->post('pengawas/reset', 'PengawasController::reset');
    $routes->post('pengawas/hapus/(:num)', 'PengawasController::hapus/$1');
    $routes->get('pengawas/export', 'PengawasController::export');

    $routes->get('daftar_ruang', 'Ruang::index');
    $routes->post('daftar_ruang/simpan', 'Ruang::save');
    $routes->post('daftar_ruang/hapus/(:num)', 'Ruang::delete/$1');
    $routes->post('daftar_ruang/sesi/simpan', 'Ruang::saveSesi');
    $routes->post('daftar_ruang/sesi/hapus/(:num)', 'Ruang::deleteSesi/$1');

    $routes->get('daftar_peserta/cbtadmin', 'Peserta::cbtadmin');
    $routes->post('daftar_peserta/cbtadmin/simpan', 'Peserta::save');
    $routes->post('daftar_peserta/cbtadmin/hapus/(:num)', 'Peserta::delete/$1');
    $routes->post('daftar_peserta/cbtadmin/massal', 'Peserta::bulk');
    $routes->post('daftar_peserta/cbtadmin/import', 'Peserta::import');
    $routes->get('daftar_peserta/cbtadmin/template', 'Peserta::template');
    $routes->get('daftar_peserta/cbtadmin/export', 'Peserta::export');
    $routes->get('daftar_peserta/moodle', 'Peserta::moodle');
    $routes->post('daftar_peserta/moodle/sinkron', 'Peserta::sync');
    $routes->post('daftar_peserta/moodle/buat', 'Peserta::createMoodle');
    $routes->post('daftar_peserta/moodle/reset_password', 'Peserta::resetPassword');

    $routes->get('status_tes', 'StatusTes::index');
    $routes->get('status_tes/token', 'StatusTes::token');
    $routes->get('token', 'StatusTes::tokenView');
    $routes->post('status_tes/kategori', 'StatusTes::kategori');
    $routes->post('status_tes/release', 'StatusTes::release');
    $routes->post('status_tes/token_setting', 'StatusTes::tokenSetting');
    $routes->post('status_tes/reset', 'StatusTes::reset');
    $routes->post('status_tes/waktu', 'StatusTes::waktu');
    $routes->get('status_peserta', 'StatusPeserta::index');
    $routes->post('status_peserta/aksi', 'StatusPeserta::aksi');
    $routes->get('status_peserta/(:segment)', 'StatusPeserta::index/$1');

    $routes->get('reset_login', 'ResetLogin::index');
    $routes->post('reset_login/reset', 'ResetLogin::reset');
    $routes->get('noattempt_check', 'NoAttempt::index');
    $routes->get('noattempt_check/export', 'NoAttempt::export');

    $routes->get('dokumen/cetak_doc', 'Dokumen::cetak');
    $routes->get('dokumen/cetak_doc/kartu', 'Dokumen::kartu');
    $routes->get('dokumen/cetak_doc/daftar_hadir', 'Dokumen::daftarHadir');
    $routes->get('dokumen/laporan_kehadiran', 'Dokumen::kehadiran');
    $routes->get('dokumen/laporan_kehadiran/export', 'Dokumen::kehadiranExport');
    $routes->get('dokumen/laporan_kehadiran/berita_acara', 'Dokumen::beritaAcara');

    $routes->get('hasil_ujian', 'HasilUjian::index');
    $routes->get('hasil_ujian/export', 'HasilUjian::export');
    $routes->get('hasil_ujian/pdf', 'HasilUjian::pdf');
});
