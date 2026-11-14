<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$tanpaAkun = count(array_filter($rows, static fn ($r) => $r['admin_id'] === null));
$adaAkun   = count($rows) - $tanpaAkun;
?>
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold">Akun pengawas per ruang</span>
        <span class="text-muted small"><?= $adaAkun ?> akun · <?= count($rows) ?> ruang</span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <form method="post" action="<?= site_url('pengawas/generate') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-sm" type="submit" <?= $tanpaAkun === 0 ? 'disabled' : '' ?>>
                    <i class="bi bi-person-plus me-1"></i>Buat akun<?= $tanpaAkun > 0 ? ' (' . $tanpaAkun . ' ruang)' : '' ?>
                </button>
            </form>
            <button class="btn btn-outline-secondary btn-sm" type="button" <?= $adaAkun === 0 ? 'disabled' : '' ?>
                    data-post="<?= site_url('pengawas/reset') ?>"
                    data-confirm-text="Buat password baru untuk SEMUA akun pengawas? Password lama tidak berlaku lagi.">
                <i class="bi bi-key me-1"></i>Password baru semua
            </button>
            <a class="btn btn-outline-success btn-sm <?= $adaAkun === 0 ? 'disabled' : '' ?>" href="<?= site_url('pengawas/export') ?>">
                <i class="bi bi-file-earmark-excel me-1"></i>Excel
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($rows === []): ?>
            <p class="text-muted mb-0">
                Belum ada ruang. Ruang dibuat otomatis saat <a href="<?= site_url('daftar_peserta/cbtadmin') ?>">import peserta</a>
                (kolom Ruang), atau tambahkan di <a href="<?= site_url('daftar_ruang') ?>">Daftar Ruang</a>.
            </p>
        <?php else: ?>
            <p class="small text-muted">
                Setiap ruang punya satu akun pengawas. Pengawas hanya melihat siswa di ruangnya (Dashboard, Status Peserta,
                Reset Login) dan token. Akun dibuat otomatis setiap import peserta menambah ruang baru.
            </p>
            <table class="table table-hover align-middle w-100 datatable" data-order-col="0">
                <thead>
                <tr><th>Ruang</th><th class="text-center">Peserta</th><th>Nama Pengawas</th><th>Username</th><th>Password</th><th>Login terakhir</th><th data-orderable="false"></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td data-order="<?= esc(str_pad($r['ruang_nama'], 10, '0', STR_PAD_LEFT)) ?>"><?= esc($r['ruang_nama']) ?></td>
                        <td class="text-center"><?= (int) $r['jumlah_peserta'] ?></td>
                        <?php if ($r['admin_id'] === null): ?>
                            <td class="text-muted">Belum ada akun</td><td>–</td><td>–</td><td>–</td><td></td>
                        <?php else: ?>
                            <td><?= esc($r['nama']) ?><?= (int) $r['aktif'] ? '' : ' <span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                            <td><code><?= esc($r['username']) ?></code></td>
                            <td><code><?= esc($r['password_plain'] ?? '–') ?></code></td>
                            <td><?= $r['last_login'] ? esc(date('d/m/Y H:i', strtotime($r['last_login']))) : '–' ?></td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-outline-secondary btn-sm" type="button" title="Password baru"
                                        data-post="<?= site_url('pengawas/reset') ?>" data-fields="<?= esc(json_encode(['id' => $r['admin_id']]), 'attr') ?>"
                                        data-confirm-text="Buat password baru untuk <?= esc($r['username'], 'attr') ?>?"><i class="bi bi-key"></i></button>
                                <button class="btn btn-outline-danger btn-sm" type="button" title="Hapus akun"
                                        data-post="<?= site_url('pengawas/hapus/' . (int) $r['admin_id']) ?>"
                                        data-confirm-text="Hapus akun <?= esc($r['username'], 'attr') ?>? Pengawas ruang ini tidak bisa login sampai akun dibuat lagi."><i class="bi bi-trash"></i></button>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</div>

<form method="post" id="postForm" class="d-none"><?= csrf_field() ?></form>
<?= $this->endSection() ?>
