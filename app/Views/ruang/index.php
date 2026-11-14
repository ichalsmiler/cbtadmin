<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$warna = ['primary', 'dark', 'danger', 'success', 'warning', 'info', 'secondary'];
$waktu = static fn (?string $d): string => $d ? date('d/m H:i', strtotime($d)) : 'belum pernah';
?>
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold"><i class="bi bi-door-open me-1"></i>Daftar Ruang</span>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" type="button" data-ruang='{"id":"","nama":"","kapasitas":"","pengawas":"","sesi_id":""}'>
                <i class="bi bi-plus-lg me-1"></i>Tambah Ruang
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('daftar_ruang/cetak') ?>" target="_blank">
                <i class="bi bi-printer me-1"></i>Cetak ID Login
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($ruang === []): ?>
            <p class="text-muted mb-0">Belum ada ruang. Ruang dibuat otomatis saat impor peserta, atau klik <strong>Tambah Ruang</strong>.</p>
        <?php else: ?>
            <form method="post" action="<?= site_url('daftar_ruang/lokasi') ?>" id="lokasiForm"><?= csrf_field() ?></form>
            <div class="table-responsive">
                <table class="table align-middle mb-0 ruang-table">
                    <thead>
                    <tr>
                        <th class="text-center" style="width:3rem">No</th>
                        <th>Kode Login</th>
                        <th>Password</th>
                        <th style="min-width:14rem">Nama Ruang</th>
                        <th class="text-center">Peserta</th>
                        <th class="text-center">Tukar</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ruang as $i => $r): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $i + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="ruang-avatar text-bg-<?= $warna[$i % count($warna)] ?>"><?= chr(65 + $i % 26) ?></span>
                                    <div>
                                        <div class="fw-semibold"><?= esc($r['username'] ?? '–') ?></div>
                                        <div class="small text-primary">Ruang <?= esc($r['nama']) ?><?= $r['sesi_nama'] ? ' · ' . esc($r['sesi_nama']) : '' ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= esc((string) $r['password_plain']) ?></code></td>
                            <td>
                                <input form="lokasiForm" class="form-control form-control-sm" name="lokasi[<?= (int) $r['id'] ?>]"
                                       value="<?= esc($r['lokasi'], 'attr') ?>" maxlength="120" placeholder="Masukkan nama ruang / lab"
                                       aria-label="Nama ruang untuk Ruang <?= esc($r['nama'], 'attr') ?>">
                            </td>
                            <td class="text-center"><?= (int) $r['jumlah_peserta'] ?><?= (int) $r['kapasitas'] > 0 ? ' / ' . (int) $r['kapasitas'] : '' ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-link" type="button" title="Tukar Akses: masuk sebagai pengawas Ruang <?= esc($r['nama'], 'attr') ?>"
                                        data-post="<?= site_url('daftar_ruang/tukar/' . $r['id']) ?>"
                                        data-confirm-text="Tukar akses sebagai pengawas Ruang <?= esc($r['nama'], 'attr') ?>? Kembali ke admin lewat tombol di atas halaman.">
                                    <i class="bi bi-arrow-repeat fs-5"></i>
                                </button>
                            </td>
                            <td class="text-center text-nowrap">
                                <button class="btn btn-sm btn-link" type="button" title="Generate Kode: buat password pengawas baru"
                                        data-post="<?= site_url('daftar_ruang/kode/' . $r['id']) ?>"
                                        data-confirm-text="Buat password baru untuk pengawas Ruang <?= esc($r['nama'], 'attr') ?>? Password lama tidak berlaku lagi.">
                                    <i class="bi bi-key fs-5"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-sm btn-link text-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Lainnya">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item" type="button"
                                                    data-ruang="<?= esc(json_encode(['id' => $r['id'], 'nama' => $r['nama'], 'kapasitas' => $r['kapasitas'], 'pengawas' => $r['pengawas'], 'sesi_id' => $r['sesi_id'] ?? '']), 'attr') ?>">
                                                <i class="bi bi-pencil me-2"></i>Ubah ruang / sesi</button></li>
                                        <li><button class="dropdown-item text-danger" type="button"
                                                    data-post="<?= site_url('daftar_ruang/hapus/' . $r['id']) ?>"
                                                    data-confirm-text="Hapus Ruang <?= esc($r['nama'], 'attr') ?>? Peserta di ruang ini menjadi tanpa ruang, dan akun pengawasnya ikut dihapus.">
                                                <i class="bi bi-trash me-2"></i>Hapus ruang</button></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <button class="btn btn-success mt-3" type="submit" form="lokasiForm"><i class="bi bi-save me-1"></i>Simpan</button>
            <p class="small text-muted mt-3 mb-0">
                <strong>Kode Login</strong> dan password dipakai pengawas untuk login ke CBT Admin di
                <code><?= esc(site_url('login')) ?></code>; bagikan lewat <strong>Cetak ID Login</strong>.
                <strong>Tukar</strong> = masuk sebagai pengawas ruang itu untuk melihat tampilannya.
                <strong>Aksi</strong> (ikon kunci) = buat password baru bila kartu login hilang.
            </p>
        <?php endif ?>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold"><i class="bi bi-clock me-1"></i>Sesi</div>
    <ul class="list-group list-group-flush">
        <?php if ($sesi === []): ?>
            <li class="list-group-item text-muted">Belum ada sesi.</li>
        <?php endif ?>
        <?php foreach ($sesi as $s): ?>
            <li class="list-group-item d-flex align-items-center">
                <span><?= esc($s['nama']) ?>
                    <?php if ($s['jam_mulai']): ?>
                        <small class="text-muted ms-1"><?= esc(substr($s['jam_mulai'], 0, 5)) ?>–<?= esc(substr((string) $s['jam_selesai'], 0, 5)) ?></small>
                    <?php endif ?>
                </span>
                <button class="btn btn-outline-danger btn-sm ms-auto" type="button" title="Hapus"
                        data-post="<?= site_url('daftar_ruang/sesi/hapus/' . $s['id']) ?>"
                        data-confirm-text="Hapus sesi &quot;<?= esc($s['nama'], 'attr') ?>&quot;?"><i class="bi bi-trash"></i></button>
            </li>
        <?php endforeach ?>
    </ul>
    <div class="card-body border-top">
        <form method="post" action="<?= site_url('daftar_ruang/sesi/simpan') ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-sm-5"><input class="form-control form-control-sm" name="nama" placeholder="Nama sesi, mis. Sesi 1" maxlength="60" required aria-label="Nama sesi"></div>
            <div class="col-6 col-sm-2"><input class="form-control form-control-sm" type="time" name="jam_mulai" aria-label="Jam mulai"></div>
            <div class="col-6 col-sm-2"><input class="form-control form-control-sm" type="time" name="jam_selesai" aria-label="Jam selesai"></div>
            <div class="col-sm-3"><button class="btn btn-primary btn-sm w-100" type="submit">Tambah Sesi</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="ruangModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('daftar_ruang/simpan') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Ruang</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nomor / nama ruang ujian</label><input class="form-control" name="nama" maxlength="60" required placeholder="mis. 1"></div>
                <div class="mb-3"><label class="form-label">Kapasitas</label><input class="form-control" type="number" name="kapasitas" min="0"></div>
                <div class="mb-3"><label class="form-label">Nama pengawas (untuk dokumen)</label><input class="form-control" name="pengawas" maxlength="120"></div>
                <div><label class="form-label">Sesi</label>
                    <select class="form-select" name="sesi_id">
                        <option value="">(tanpa sesi)</option>
                        <?php foreach ($sesi as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= esc($s['nama']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<form method="post" id="postForm" class="d-none"><?= csrf_field() ?></form>
<?= $this->endSection() ?>
