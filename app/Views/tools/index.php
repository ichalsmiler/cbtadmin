<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header fw-semibold">Pengaturan kegiatan dan identitas sekolah</div>
    <div class="card-body">
        <form method="post" action="<?= site_url('tools') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 d-flex flex-wrap gap-3 align-items-end">
                    <?php if ($hasLogo): ?>
                        <img src="<?= site_url('tools/logo') ?>?t=<?= time() ?>" alt="Logo sekolah" style="height: 64px; width: auto" class="border rounded p-1 bg-white">
                    <?php endif ?>
                    <div>
                        <label class="form-label" for="f_logo">Logo sekolah (PNG/JPG, maks. 1 MB)</label>
                        <input class="form-control" type="file" id="f_logo" name="logo" accept="image/png,image/jpeg">
                    </div>
                    <?php if ($hasLogo): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="f_hapus_logo" name="hapus_logo" value="1">
                            <label class="form-check-label" for="f_hapus_logo">Hapus logo</label>
                        </div>
                    <?php endif ?>
                </div>
                <?php foreach ($fields as $key => $label): ?>
                    <?php $numeric = in_array($key, ['refresh_interval', 'active_minutes'], true); ?>
                    <div class="<?= $key === 'nama_kegiatan' || $key === 'alamat' ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label" for="f_<?= $key ?>"><?= esc($label) ?></label>
                        <input class="form-control" id="f_<?= $key ?>" name="<?= $key ?>" maxlength="255"
                               type="<?= $numeric ? 'number' : 'text' ?>" <?= $numeric ? 'min="1"' : '' ?>
                               value="<?= esc($values[$key] ?? '') ?>"
                               <?= $key === 'nama_kegiatan' ? 'placeholder="mis. PAS Ganjil 2026/2027"' : '' ?>>
                    </div>
                <?php endforeach ?>
            </div>
            <button class="btn btn-primary mt-4" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
        </form>
    </div>
</div>

<div class="card mt-4" id="akun">
    <div class="card-header d-flex align-items-center">
        <span class="fw-semibold">Akun panel</span>
        <button class="btn btn-primary btn-sm ms-auto" type="button" data-fill-modal="#akunModal"
                data-fill='{"id":"","username":"","nama":"","role":"proktor","aktif":"1","password":""}'>
            <i class="bi bi-person-plus me-1"></i>Tambah akun
        </button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td><code><?= esc($a['username']) ?></code><?= (int) $a['id'] === $meId ? ' <span class="badge text-bg-light">Anda</span>' : '' ?></td>
                    <td><?= esc($a['nama']) ?></td>
                    <td><?= $a['role'] === 'admin' ? 'Administrator' : 'Proktor' ?></td>
                    <td><?= $a['aktif'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                    <td><?= $a['last_login'] ? esc(date('d/m/Y H:i', strtotime($a['last_login']))) : '–' ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-outline-secondary btn-sm" type="button" title="Ubah" data-fill-modal="#akunModal"
                                data-fill="<?= esc(json_encode(['id' => $a['id'], 'username' => $a['username'], 'nama' => $a['nama'], 'role' => $a['role'], 'aktif' => (string) $a['aktif'], 'password' => '']), 'attr') ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ((int) $a['id'] !== $meId): ?>
                            <button class="btn btn-outline-danger btn-sm" type="button" title="Hapus"
                                    data-post="<?= site_url('tools/akun/hapus/' . $a['id']) ?>"
                                    data-confirm-text="Hapus akun <?= esc($a['username'], 'attr') ?>? Akun ini tidak bisa login lagi."><i class="bi bi-trash"></i></button>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-body small text-muted border-top">Proktor bisa memakai semua menu kecuali Tools.</div>
</div>

<div class="card mt-4" id="utilitas">
    <div class="card-header fw-semibold">Utilitas</div>
    <div class="card-body d-flex flex-wrap gap-4">
        <div style="max-width: 420px">
            <a class="btn btn-outline-primary" href="<?= site_url('tools/backup') ?>"><i class="bi bi-database-down me-1"></i>Backup database panel</a>
            <p class="small text-muted mt-2 mb-0">Mengunduh berkas .sql berisi peserta, ruang, sesi, pengaturan, akun, dan log panel. Berkas ini memuat password peserta, jadi simpan di tempat aman. Data Moodle tidak termasuk.</p>
        </div>
        <div style="max-width: 420px">
            <button class="btn btn-outline-primary" type="button" data-post="<?= site_url('tools/bersihkan_sesi') ?>"
                    data-confirm-text="Hapus sesi panel dan sesi Moodle yang sudah kedaluwarsa? Pengguna yang masih aktif tidak terpengaruh.">
                <i class="bi bi-trash3 me-1"></i>Bersihkan sesi kedaluwarsa
            </button>
            <p class="small text-muted mt-2 mb-0">Menghapus sesi login yang sudah melewati batas waktu. Moodle juga melakukannya otomatis lewat cron.</p>
        </div>
    </div>
</div>

<div class="card mt-4" id="log">
    <div class="card-header fw-semibold">Log aktivitas (300 terakhir)</div>
    <div class="card-body">
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
                    <select class="form-select" name="role"><option value="admin">Administrator</option><option value="proktor">Proktor</option></select>
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
