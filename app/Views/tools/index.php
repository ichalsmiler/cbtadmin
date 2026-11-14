<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$v      = static fn (string $k, string $def = ''): string => (string) ($values[$k] ?? $def);
$on     = static fn (string $k): bool => ($values[$k] ?? '0') === '1';
$antiSubmit = (int) $v('anti_submit', '0');
/** Satu item accordion: [id, ikon, judul, isi]. */
$item = static function (string $grup, string $id, string $ikon, string $judul, string $isi): string {
    return '<div class="accordion-item" id="' . $id . '">'
        . '<h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c_' . $id . '" aria-expanded="false" aria-controls="c_' . $id . '">'
        . '<i class="bi bi-' . $ikon . ' me-2 text-primary"></i>' . esc($judul) . '</button></h2>'
        . '<div id="c_' . $id . '" class="accordion-collapse collapse" data-bs-parent="#' . $grup . '"><div class="accordion-body">' . $isi . '</div></div></div>';
};
$csrf = csrf_field();
$input = static fn (string $name, string $label, string $value, string $ph = '', string $col = 'col-md-6', string $type = 'text'): string =>
    '<div class="' . $col . '"><label class="form-label" for="f_' . $name . '">' . esc($label) . '</label>'
    . '<input class="form-control" type="' . $type . '" id="f_' . $name . '" name="' . $name . '" maxlength="255" value="' . esc($value, 'attr') . '" placeholder="' . esc($ph, 'attr') . '"></div>';
?>

<div class="row g-4">
<div class="col-xl-6">
<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-mortarboard me-1"></i>Moodle Tools</div>
    <div class="accordion accordion-flush" id="moodleTools">
        <?= $item('moodleTools', 'testmapel', 'calendar3', 'Daftar Test / Mapel',
            '<p class="small text-muted">Menambahkan kategori ujian (test) dan mata pelajaran di Moodle. Kategori dan mapel lalu diaktifkan di Status Test.</p>'
            . '<a class="btn btn-success btn-sm" href="' . site_url('tools/test_manage') . '"><i class="bi bi-list-task me-1"></i>Kelola Test</a>') ?>
        <?= $item('moodleTools', 'cron', 'gear', 'Cron Task',
            '<p class="small text-muted">Menjalankan cron task Moodle sekarang (mis. menutup attempt yang lewat waktu, mengirim pekerjaan tertunda). Cron juga berjalan otomatis tiap menit.</p>'
            . '<button class="btn btn-info btn-sm" type="button" data-post="' . site_url('tools/cron') . '" data-confirm-text="Jalankan cron task Moodle sekarang? Bisa memakan waktu hingga beberapa menit."><i class="bi bi-rocket-takeoff me-1"></i>Cron</button>') ?>
        <?= $item('moodleTools', 'cache', 'layers', 'Purge / Delete Caches',
            '<p class="small text-muted">Membersihkan berkas cache Moodle di server. <strong>Purge</strong> = cara biasa; <strong>Delete</strong> = hapus seluruh folder cache lalu dibangun ulang (dipakai bila tampilan Moodle bermasalah). Halaman pertama sesudahnya akan lebih lambat.</p>'
            . '<button class="btn btn-warning btn-sm me-1" type="button" data-post="' . site_url('tools/cache') . '" data-fields=\'{"jenis":"purge"}\' data-confirm-text="Purge cache Moodle sekarang?"><i class="bi bi-arrow-repeat me-1"></i>Purge</button>'
            . '<button class="btn btn-danger btn-sm" type="button" data-post="' . site_url('tools/cache') . '" data-fields=\'{"jenis":"delete"}\' data-confirm-text="Hapus semua cache Moodle? Jangan dilakukan saat ujian berlangsung."><i class="bi bi-trash3 me-1"></i>Delete</button>') ?>
        <?= $item('moodleTools', 'antisubmit', 'hourglass-split', 'Anti Submit',
            '<p class="small text-muted">Mencegah peserta menyelesaikan ujian sebelum waktunya. Contoh: anti submit 10 menit dan durasi ujian 120 menit, peserta baru bisa submit setelah mengerjakan 110 menit. Isi <strong>0</strong> untuk menonaktifkan.</p>'
            . '<form method="post" action="' . site_url('tools/ujian') . '" class="d-flex flex-wrap align-items-center gap-2">' . $csrf
            . '<input type="hidden" name="bagian" value="antisubmit">'
            . '<span class="small">Peserta bisa submit</span><input class="form-control form-control-sm" style="width:6rem" type="number" min="0" max="1000" name="anti_submit" value="' . $antiSubmit . '" aria-label="Menit anti submit">'
            . '<span class="small">menit sebelum waktu ujian selesai.</span>'
            . '<button class="btn btn-success btn-sm" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>'
            . '<p class="small mt-2 mb-0">Status: ' . ($antiSubmit > 0 && $antiSubmit < 1000 ? '<span class="badge text-bg-success">aktif, ' . $antiSubmit . ' menit</span>' : '<span class="badge text-bg-secondary">nonaktif</span>') . '</p>') ?>
    </div>
</div>
</div>

<div class="col-xl-6">
<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-tools me-1"></i>CBTAdmin Tools</div>
    <div class="accordion accordion-flush" id="cbtTools">
        <?php
        $logoPrev = $hasLogo ? '<img src="' . site_url('tools/gambar/logo') . '?t=' . time() . '" alt="Logo" class="border rounded p-1 bg-white me-2" style="height:48px">' : '';
        $ttdPrev  = $hasTtd ? '<img src="' . site_url('tools/gambar/ttd') . '?t=' . time() . '" alt="Tanda tangan" class="border rounded p-1 bg-white me-2" style="height:48px">' : '';
        $hapus    = static fn (string $j, bool $ada): string => $ada ? '<div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="h_' . $j . '" name="hapus_' . $j . '" value="1"><label class="form-check-label small" for="h_' . $j . '">Hapus</label></div>' : '';
        ?>
        <?= $item('cbtTools', 'sekolah', 'building', 'Info Sekolah',
            '<form method="post" action="' . site_url('tools') . '" enctype="multipart/form-data">' . $csrf
            . '<input type="hidden" name="bagian" value="sekolah"><div class="row g-3">'
            . $input('nama_sekolah', 'Nama sekolah', $v('nama_sekolah'), '', 'col-md-8')
            . $input('npsn', 'NPSN', $v('npsn'), '', 'col-md-4')
            . $input('alamat', 'Alamat sekolah', $v('alamat'), '', 'col-md-8')
            . $input('kota', 'Kota (tempat tanda tangan)', $v('kota'), '', 'col-md-4')
            . '<div class="col-12 small text-muted mb-n2">Profil pimpinan: jabatan, nama, dan nomor pegawai.</div>'
            . $input('jabatan_pimpinan', 'Jabatan', $v('jabatan_pimpinan', 'Kepala Sekolah'), 'Kepala Sekolah / Kepala Madrasah', 'col-md-6')
            . $input('kepala_sekolah', 'Nama pimpinan', $v('kepala_sekolah'), 'Nama Pimpinan', 'col-md-6')
            . $input('jenis_nomor_pimpinan', 'Jenis nomor', $v('jenis_nomor_pimpinan', 'NIP'), 'NIP / NIY / dsb', 'col-md-4')
            . $input('nip_kepala', 'Nomor', $v('nip_kepala'), '12341234 123123 1 123', 'col-md-8')
            . '<div class="col-md-6"><label class="form-label" for="f_logo">Logo sekolah</label><div class="d-flex align-items-center">' . $logoPrev
            . '<input class="form-control form-control-sm" type="file" id="f_logo" name="logo" accept="image/png,image/jpeg"></div>' . $hapus('logo', $hasLogo) . '</div>'
            . '<div class="col-md-6"><label class="form-label" for="f_ttd">Tanda tangan pimpinan</label><div class="d-flex align-items-center">' . $ttdPrev
            . '<input class="form-control form-control-sm" type="file" id="f_ttd" name="ttd" accept="image/png,image/jpeg"></div>' . $hapus('ttd', $hasTtd) . '</div>'
            . '<div class="col-12 small text-muted">Tips: logo sekitar 100×100 piksel, tanda tangan sekitar 125×45 piksel (PNG berlatar transparan lebih rapi). Maksimal 1 MB.</div>'
            . '</div><button class="btn btn-success btn-sm mt-3" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>') ?>
        <?= $item('cbtTools', 'kegiatan', 'calendar-event', 'Info Kegiatan',
            '<p class="small text-muted">Nama kegiatan yang dilaksanakan (contoh: Penilaian Akhir Tahun, Penilaian Akhir Semester, Ujian Sekolah). Tampil di kartu peserta dan dokumen.</p>'
            . '<form method="post" action="' . site_url('tools') . '">' . $csrf . '<input type="hidden" name="bagian" value="kegiatan"><div class="row g-3">'
            . $input('nama_kegiatan', 'Nama kegiatan', $v('nama_kegiatan'), 'Nama Kegiatan', 'col-md-8')
            . $input('tahun_ajaran', 'Tahun pelajaran', $v('tahun_ajaran'), 'Cth: 2026/2027', 'col-md-4')
            . $input('alamat_server', 'Alamat server ujian', $v('alamat_server'), 'Cth: http://172.16.32.154', 'col-12')
            . '</div><button class="btn btn-success btn-sm mt-3" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>') ?>
        <?= $item('cbtTools', 'lockres', 'shield-lock', 'Lock login / Resume timer / Anti Newtab',
            '<form method="post" action="' . site_url('tools/ujian') . '">' . $csrf . '<input type="hidden" name="bagian" value="aturan">'
            . '<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="f_lock" name="lock_login" value="1"' . ($on('lock_login') ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="f_lock"><strong>Lock login</strong> — peserta harus meminta reset ke pengawas bila masih berstatus login di perangkat lain. Bila mati, login di perangkat baru otomatis mengeluarkan perangkat lama.</label></div>'
            . '<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="f_resume" name="resume_timer" value="1"' . ($on('resume_timer') ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="f_resume"><strong>Resume timer</strong> — peserta yang keluar (komputer mati, pindah komputer) dapat melanjutkan ujian dengan sisa waktunya; waktu selama keluar ditambahkan saat ia login lagi, selama waktu ujiannya belum habis.</label></div>'
            . '<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="f_newtab" name="anti_newtab" value="1"' . ($on('anti_newtab') ? ' checked' : '') . '>'
            . '<label class="form-check-label" for="f_newtab"><strong>Anti Newtab</strong> — peserta otomatis keluar bila membuka tab baru atau aplikasi lain saat mengerjakan quiz. Jawaban yang sudah tersimpan tetap aman.</label></div>'
            . '<button class="btn btn-success btn-sm" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>') ?>
        <?= $item('cbtTools', 'password', 'key', 'Ganti Password',
            '<p class="small text-muted">Mengganti password akun Anda. Password pengawas diganti lewat menu Daftar Ruang (Generate Kode).</p>'
            . '<form method="post" action="' . site_url('tools/password') . '" class="d-flex flex-wrap gap-2" autocomplete="off">' . $csrf
            . '<input class="form-control form-control-sm" style="max-width:16rem" type="password" name="password" minlength="8" required placeholder="Password baru (min. 8 karakter)" autocomplete="new-password" aria-label="Password baru">'
            . '<button class="btn btn-success btn-sm" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>'
            . '<p class="small text-danger mt-2 mb-0">Peringatan! Catat password baru untuk menghindari lupa password.</p>') ?>
    </div>
</div>
</div>
</div>

<div class="card mt-4">
    <div class="card-header fw-semibold"><i class="bi bi-three-dots me-1"></i>Lainnya</div>
    <div class="accordion accordion-flush" id="lainTools">
        <?php ob_start(); ?>
        <div class="d-flex mb-2">
            <span class="small text-muted">Akun administrator dan proktor panel. Akun pengawas ruang dibuat otomatis di Daftar Ruang.</span>
            <button class="btn btn-primary btn-sm ms-auto" type="button" data-fill-modal="#akunModal"
                    data-fill='{"id":"","username":"","nama":"","role":"admin","aktif":"1","password":""}'><i class="bi bi-person-plus me-1"></i>Tambah akun</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($admins as $a): ?>
                    <tr>
                        <td class="text-nowrap"><code><?= esc($a['username']) ?></code><?= (int) $a['id'] === $meId ? ' <span class="badge text-bg-light">Anda</span>' : '' ?></td>
                        <td><?= esc($a['nama']) ?></td>
                        <td><?= $a['role'] === 'admin' ? 'Administrator' : 'Pengawas' ?></td>
                        <td><?= $a['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                        <td class="text-nowrap"><?= $a['last_login'] ? esc(date('d/m/Y H:i', strtotime($a['last_login']))) : '–' ?></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-outline-secondary btn-sm" type="button" title="Ubah" data-fill-modal="#akunModal"
                                    data-fill="<?= esc(json_encode(['id' => $a['id'], 'username' => $a['username'], 'nama' => $a['nama'], 'role' => $a['role'], 'aktif' => (string) $a['aktif'], 'password' => '']), 'attr') ?>"><i class="bi bi-pencil"></i></button>
                            <?php if ((int) $a['id'] !== $meId): ?>
                                <button class="btn btn-outline-danger btn-sm" type="button" title="Hapus" data-post="<?= site_url('tools/akun/hapus/' . $a['id']) ?>"
                                        data-confirm-text="Hapus akun <?= esc($a['username'], 'attr') ?>? Akun ini tidak bisa login lagi."><i class="bi bi-trash"></i></button>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $item('lainTools', 'akun', 'people', 'Akun panel', (string) ob_get_clean()) ?>

        <?= $item('lainTools', 'panel', 'sliders', 'Pengaturan panel',
            '<form method="post" action="' . site_url('tools') . '">' . $csrf . '<input type="hidden" name="bagian" value="panel"><div class="row g-3">'
            . $input('refresh_interval', 'Interval refresh Status Peserta (detik)', $v('refresh_interval', '30'), '30', 'col-md-4', 'number')
            . $input('active_minutes', 'User dianggap aktif dalam (menit)', $v('active_minutes', '5'), '5', 'col-md-4', 'number')
            . $input('webmin_url', 'URL Webmin', $v('webmin_url'), '', 'col-md-4')
            . '</div><button class="btn btn-success btn-sm mt-3" type="submit"><i class="bi bi-save me-1"></i>Simpan</button></form>') ?>

        <?= $item('lainTools', 'utilitas', 'database', 'Backup dan sesi',
            '<div class="d-flex flex-wrap gap-4"><div style="max-width:420px"><a class="btn btn-outline-primary btn-sm" href="' . site_url('tools/backup') . '"><i class="bi bi-database-down me-1"></i>Backup database panel</a>'
            . '<p class="small text-muted mt-2 mb-0">Mengunduh berkas .sql berisi peserta, ruang, sesi, pengaturan, akun, dan log panel. Berkas ini memuat password peserta, jadi simpan di tempat aman. Data Moodle tidak termasuk.</p></div>'
            . '<div style="max-width:420px"><button class="btn btn-outline-primary btn-sm" type="button" data-post="' . site_url('tools/bersihkan_sesi') . '" data-confirm-text="Hapus sesi panel dan sesi Moodle yang sudah kedaluwarsa? Pengguna yang masih aktif tidak terpengaruh."><i class="bi bi-trash3 me-1"></i>Bersihkan sesi kedaluwarsa</button>'
            . '<p class="small text-muted mt-2 mb-0">Menghapus sesi login yang sudah melewati batas waktu. Moodle juga melakukannya otomatis lewat cron.</p></div></div>') ?>

        <?php ob_start(); ?>
        <table class="table table-sm align-middle w-100 datatable" data-order-col="0">
            <thead><tr><th>Waktu</th><th>Akun</th><th>Aksi</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td data-order="<?= (int) $l['id'] ?>" class="text-nowrap"><?= esc(date('d/m H:i:s', strtotime((string) $l['created_at']))) ?></td>
                    <td><?= esc($l['username']) ?></td>
                    <td><code><?= esc($l['aksi']) ?></code></td>
                    <td class="small"><?= esc((string) $l['detail']) ?></td>
                    <td class="small"><?= esc($l['ip']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
        <?= $item('lainTools', 'log', 'journal-text', 'Log aktivitas (300 terakhir)', (string) ob_get_clean()) ?>
    </div>
</div>

<form method="post" id="postForm" class="d-none"><?= csrf_field() ?></form>

<div class="modal fade" id="akunModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('tools/akun/simpan') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Akun panel</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body row g-3">
                <div class="col-sm-6"><label class="form-label">Username</label><input class="form-control" name="username" maxlength="60" pattern="[A-Za-z0-9._\-]{3,60}" required></div>
                <div class="col-sm-6"><label class="form-label">Nama</label><input class="form-control" name="nama" maxlength="120" required></div>
                <div class="col-sm-6"><label class="form-label">Role</label>
                    <select class="form-select" name="role"><option value="admin">Administrator</option><option value="proktor">Pengawas</option></select>
                </div>
                <div class="col-sm-6"><label class="form-label">Status</label>
                    <select class="form-select" name="aktif"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
                </div>
                <div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="minimal 8 karakter; kosongkan bila tidak diganti"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';
    // Buka bagian sesuai #anchor (mis. setelah menyimpan), atau dari tautan.
    var buka = function () {
        var id = location.hash.replace('#', '');
        var el = id && document.getElementById('c_' + id);
        if (el && window.bootstrap) {
            bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
            document.getElementById(id).scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    };
    window.addEventListener('hashchange', buka);
    buka();
})();
</script>
<?= $this->endSection() ?>
