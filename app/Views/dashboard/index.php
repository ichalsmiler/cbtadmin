<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
// [kunci statistik, label, warna, ikon, tautan]
$cards = $isAdmin ? [
    ['ruang', 'Total Ruang', 'teal', 'door-open', 'daftar_ruang'],
    ['users', 'Total User', 'yellow', 'people', 'daftar_peserta/moodle'],
    ['aktif', 'User Aktif', 'indigo', 'person-check', 'reset_login'],
    ['belum', 'Belum Submit', 'pink', 'hourglass-split', 'status_peserta/inprogress'],
    ['sudah', 'Sudah Submit', 'green', 'check2-circle', 'status_peserta/finished'],
] : [
    // Pengawas: sesi yang sedang ujian (mis. "Sesi Aktif (Kelas X)") dan jumlah siswa ruangnya di sesi itu.
    ['ruang', $myRuang !== null ? 'Sesi Aktif' . ($sesiAktif !== null && $sesiAktif['tingkat'] !== '' ? ' (Kelas ' . $sesiAktif['tingkat'] . ')' : '') : 'Total Ruang', 'teal', 'calendar-check', null],
    ['users', $myRuang !== null ? ($sesiAktif !== null ? 'Siswa di Ruang (sesi aktif)' : 'Siswa di Ruang') : 'Total Siswa', 'yellow', 'people', 'peserta_ruang'],
    ['belum', 'Belum Submit', 'pink', 'hourglass-split', 'status_peserta/inprogress'],
    ['sudah', 'Sudah Submit', 'green', 'check2-circle', 'status_peserta/finished'],
];
$bars = $isAdmin ? [
    ['cpu', 'CPU', $stats['sys']['cpu']['cores'] . ' core · ' . $stats['sys']['cpu']['model']],
    ['mem', 'Memory', $stats['sys']['mem']['text']],
    ['disk', 'Disk', $stats['sys']['disk']['text']],
] : [];
?>

<?php if ($isAdmin && $namaKegiatan === ''): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        Nama Kegiatan belum diatur (<a class="alert-link" href="<?= site_url('tools') ?>">Tools → Nama Kegiatan</a>).
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
<?php endif ?>

<?php if ($sesiAktif !== null): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 py-2" role="status">
        <i class="bi bi-calendar-check"></i>
        <span>Sesi aktif: <strong><?= esc($sesiAktif['label']) ?></strong></span>
        <?php if ($isAdmin): ?><a class="alert-link ms-auto small" href="<?= site_url('status_tes') ?>">Ganti sesi</a><?php endif ?>
    </div>
<?php else: ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2" role="status">
        <i class="bi bi-exclamation-triangle"></i>
        <span>Belum ada sesi yang aktif.</span>
        <?php if ($isAdmin): ?><a class="alert-link ms-auto small" href="<?= site_url('status_tes') ?>">Aktifkan di Status Test</a><?php endif ?>
    </div>
<?php endif ?>

<?php if ($token !== null): ?>
    <?= view('partials/token_card', ['token' => $token]) ?>
<?php endif ?>

<div class="alert alert-danger <?= $stats['moodleError'] ? '' : 'd-none' ?>" id="moodleError" role="alert">
    Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.
</div>

<div class="row g-3 mb-4 row-cols-2 <?= count($cards) > 4 ? 'row-cols-md-3 row-cols-xl-5' : 'row-cols-xl-4' ?>">
    <?php foreach ($cards as [$key, $label, $color, $icon, $link]): ?>
        <div class="col">
            <div class="stat-card stat-<?= $color ?>">
                <div class="stat-ribbon"><i class="bi bi-<?= $icon ?>"></i></div>
                <div class="stat-value" data-stat="<?= $key ?>"><?= $stats[$key] === null ? '–' : (is_int($stats[$key]) ? number_format($stats[$key], 0, ',', '.') : esc($stats[$key])) ?></div>
                <div class="stat-label"><?= esc($label) ?></div>
                <?php if ($link !== null): ?>
                    <a class="stat-link" href="<?= site_url($link) ?>" aria-label="Buka <?= esc($label) ?>">&raquo;</a>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php if ($isAdmin): ?>
<div class="card">
    <div class="card-header d-flex align-items-center">
        <span class="fw-semibold">System Information</span>
        <?php if ($webminUrl !== ''): ?>
            <a class="ms-auto small" href="<?= esc($webminUrl, 'attr') ?>" target="_blank" rel="noopener">Open Webmin <i class="bi bi-box-arrow-up-right"></i></a>
        <?php endif ?>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-5">
                <dl class="row mb-0 sysinfo">
                    <dt class="col-4">Name</dt><dd class="col-8"><?= esc($namaSekolah !== '' ? $namaSekolah : '-') ?></dd>
                    <dt class="col-4">ID</dt><dd class="col-8"><?= esc($npsn !== '' ? $npsn : '-') ?></dd>
                    <dt class="col-4">Address</dt><dd class="col-8"><?= esc($serverIp) ?></dd>
                </dl>
            </div>
            <div class="col-lg-7">
                <?php foreach ($bars as [$key, $label, $text]): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold"><?= $label ?></span>
                            <span class="text-muted text-truncate ms-3" data-sys-text="<?= $key ?>"><?= esc($text) ?></span>
                        </div>
                        <div class="progress" role="progressbar" aria-label="<?= $label ?>">
                            <div class="progress-bar" data-sys-bar="<?= $key ?>" style="width: <?= (int) $stats['sys'][$key]['percent'] ?>%"><?= (int) $stats['sys'][$key]['percent'] ?>%</div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    CbtDashboard.start('<?= site_url('dashboard/stats') ?>', <?= (int) $refresh ?>);
</script>
<?= $this->endSection() ?>
