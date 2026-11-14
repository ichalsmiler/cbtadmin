<?php
/** Menu sidebar: [label, ikon, path] atau [label, ikon, null, [[label, path], ...]]. */
$menu = [
    ['Dashboard', 'house', ''],
    ['Daftar Peserta', 'person', null, [['Data CBTAdmin', 'daftar_peserta/cbtadmin'], ['Data Moodle', 'daftar_peserta/moodle']]],
    ['Status Test', 'check-square', 'status_tes'],
    ['Token', 'key', 'token'],
    ['Status Peserta', 'people', 'status_peserta'],
    ['Reset Login Peserta', 'person-x', 'reset_login'],
    ['No Attempt Check', 'file-earmark-x', 'noattempt_check'],
    ['Daftar Ruang', 'door-open', 'daftar_ruang'],
    ['Dokumen', 'file-text', null, [['Kartu / Daftar Hadir', 'dokumen/cetak_doc'], ['Laporan Kehadiran', 'dokumen/laporan_kehadiran']]],
    ['Hasil Ujian', 'award', 'hasil_ujian'],
    ['Tools', 'gear', 'tools'],
    ['Keluar', 'box-arrow-right', 'logout'],
];
$isActive = static fn (string $path): bool => $path === '' ? $current === '' : ($current === $path || str_starts_with($current, $path . '/'));
$asset    = static fn (string $file): string => base_url('assets/' . $file);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?> · CBT Admin</title>
    <link rel="stylesheet" href="<?= $asset('vendor/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= $asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= $asset('vendor/dataTables.bootstrap5.min.css') ?>">
    <link rel="stylesheet" href="<?= $asset('css/panel.css') ?>?v=<?= esc($version) ?>">
</head>
<body>
<header class="topbar">
    <button class="btn btn-link text-white d-lg-none me-2 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Menu">
        <i class="bi bi-list fs-3"></i>
    </button>
    <a class="brand" href="<?= site_url('/') ?>"><i class="bi bi-pc-display-horizontal me-2"></i>CBT ADMIN</a>
    <span class="school d-none d-md-inline"><?= esc($namaSekolah) ?></span>
    <span class="role ms-auto" title="<?= esc($adminNama) ?>"><?= $adminRole === 'admin' ? 'ADMINISTRATOR' : 'PROKTOR' ?></span>
</header>

<nav class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
    <ul class="nav flex-column">
        <?php foreach ($menu as $i => $item): ?>
            <?php
            // Administrator: semua menu kecuali Token (sudah ada di Status Test).
            // Pengawas: hanya menu yang boleh dibukanya.
            if ($adminRole === 'admin' ? $item[2] === 'token' : ($item[2] === null || ! \App\Filters\Guard::allowed($adminRole, $item[2]))) {
                continue;
            }
            ?>
            <?php if (isset($item[3])): ?>
                <?php $open = (bool) array_filter($item[3], static fn ($sub) => $isActive($sub[1])); ?>
                <li class="nav-item">
                    <a class="nav-link has-sub <?= $open ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#sub<?= $i ?>" role="button" aria-expanded="<?= $open ? 'true' : 'false' ?>">
                        <i class="bi bi-<?= $item[1] ?>"></i><span><?= esc($item[0]) ?></span><i class="bi bi-chevron-right caret"></i>
                    </a>
                    <ul class="nav flex-column submenu collapse <?= $open ? 'show' : '' ?>" id="sub<?= $i ?>">
                        <?php foreach ($item[3] as $sub): ?>
                            <li><a class="nav-link <?= $isActive($sub[1]) ? 'active' : '' ?>" href="<?= site_url($sub[1]) ?>"><?= esc($sub[0]) ?></a></li>
                        <?php endforeach ?>
                    </ul>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= $isActive($item[2]) ? 'active' : '' ?>" href="<?= site_url($item[2] === '' ? '/' : $item[2]) ?>">
                        <i class="bi bi-<?= $item[1] ?>"></i><span><?= esc($item[0]) ?></span>
                    </a>
                </li>
            <?php endif ?>
        <?php endforeach ?>
    </ul>
</nav>

<main class="content">
    <h1 class="page-title"><?= esc($title) ?></h1>

    <?php foreach (['success' => 'success', 'error' => 'danger'] as $key => $class): ?>
        <?php if (session()->getFlashdata($key)): ?>
            <div class="alert alert-<?= $class ?> alert-dismissible fade show" role="alert">
                <?= esc(session()->getFlashdata($key)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif ?>
    <?php endforeach ?>

    <?= $this->renderSection('content') ?>

    <footer class="footer">
        <span>&copy; <?= date('Y') ?> CBT Admin</span>
        <a href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a>
    </footer>
</main>

<div class="modal fade" id="aboutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">CBT Admin</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <p class="mb-2">Versi <strong><?= esc($version) ?></strong></p>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnCekUpdate">Cek Update</button>
                <p class="small text-muted mt-2 mb-0 d-none" id="updateInfo">Pengecekan update otomatis belum tersedia di versi ini.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Konfirmasi</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body" id="confirmText"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmOk">Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $asset('vendor/jquery.min.js') ?>"></script>
<script src="<?= $asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $asset('vendor/dataTables.min.js') ?>"></script>
<script src="<?= $asset('vendor/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= $asset('js/panel.js') ?>?v=<?= esc($version) ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
