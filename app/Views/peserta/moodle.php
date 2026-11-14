<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php if ($moodleError): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted small"><?= count($rows) ?> user Moodle (tanpa admin dan guest). <?= (int) $pending ?> peserta Data CBTAdmin belum punya akun Moodle.</span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <form method="post" action="<?= site_url('daftar_peserta/moodle/buat') ?>"
                  data-confirm="Buat akun Moodle untuk <?= (int) $pending ?> peserta Data CBTAdmin yang belum punya? Username yang sudah ada di Moodle akan ditautkan dan passwordnya disamakan dengan Data CBTAdmin.">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-sm" type="submit" <?= $pending === 0 ? 'disabled' : '' ?>><i class="bi bi-person-plus me-1"></i>Buat user Moodle dari Data CBTAdmin</button>
            </form>
            <form method="post" action="<?= site_url('daftar_peserta/moodle/sinkron') ?>"
                  data-confirm="Salin user Moodle yang belum ada ke Data CBTAdmin?">
                <?= csrf_field() ?>
                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-left-right me-1"></i>Sinkron ke Data CBTAdmin</button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover align-middle w-100 datatable" data-order-col="0">
            <thead><tr><th>Username</th><th>Nama</th><th>Email</th><th>Cohort / Kelas</th><th>Akses terakhir</th><th>CBTAdmin</th><th data-orderable="false"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><code><?= esc($u['username']) ?></code><?= $u['suspended'] ? ' <span class="badge text-bg-warning">Ditangguhkan</span>' : '' ?></td>
                    <td><?= esc(trim($u['firstname'] . ' ' . $u['lastname'])) ?></td>
                    <td><?= esc($u['email']) ?></td>
                    <td><?= esc($u['cohort'] ?? '-') ?></td>
                    <td data-order="<?= (int) $u['lastaccess'] ?>"><?= $u['lastaccess'] ? date('d/m/Y H:i', (int) $u['lastaccess']) : 'Belum pernah' ?></td>
                    <td><?= isset($linked[$u['username']]) ? '<span class="badge text-bg-success">Ada</span>' : '<span class="badge text-bg-secondary">Belum</span>' ?></td>
                    <td class="text-end">
                        <button class="btn btn-outline-secondary btn-sm text-nowrap" type="button" data-fill-modal="#resetModal"
                                data-fill="<?= esc(json_encode(['userid' => $u['id'], 'username' => $u['username'], 'password' => '']), 'attr') ?>">
                            <i class="bi bi-key me-1"></i>Reset password
                        </button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('daftar_peserta/moodle/reset_password') ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="userid">
            <div class="modal-header"><h5 class="modal-title">Reset password</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" readonly></div>
                <div><label class="form-label">Password baru</label><input class="form-control" name="password" maxlength="100" placeholder="kosong = dibuat acak, mis. Kmtr-482"></div>
                <p class="small text-muted mt-2 mb-0">Password baru ditampilkan setelah disimpan dan ikut tercatat di Data CBTAdmin.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Reset</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
