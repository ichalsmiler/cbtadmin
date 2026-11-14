<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>
<p><a class="small" href="<?= site_url('tools#testmapel') ?>">&laquo; Kembali ke Tools</a></p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="fw-semibold"><i class="bi bi-journal-text me-1"></i>Daftar Ujian</span>
                <button class="btn btn-outline-primary btn-sm ms-auto" type="button" data-bs-toggle="modal" data-bs-target="#ujianModal">
                    <i class="bi bi-plus-lg me-1"></i>Tambah Ujian
                </button>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($kategori === []): ?>
                    <div class="list-group-item text-muted">Belum ada ujian.</div>
                <?php endif ?>
                <?php foreach ($kategori as $i => $k): ?>
                    <a class="list-group-item list-group-item-action d-flex align-items-center gap-3 <?= $aktif && (int) $aktif['id'] === (int) $k['id'] ? 'active' : '' ?>"
                       href="<?= site_url('tools/test_manage') ?>?kat=<?= (int) $k['id'] ?>">
                        <span class="ruang-avatar text-bg-<?= ['primary', 'dark', 'danger', 'success', 'warning', 'info'][$i % 6] ?>"><?= esc(mb_strtoupper(mb_substr($k['name'], 0, 1))) ?></span>
                        <span>
                            <span class="fw-semibold d-block"><?= esc($k['name']) ?></span>
                            <span class="small <?= $aktif && (int) $aktif['id'] === (int) $k['id'] ? '' : 'text-muted' ?>">Id: <?= (int) $k['id'] ?> · Total pelajaran: <?= (int) $k['coursecount'] ?></span>
                        </span>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </a>
                <?php endforeach ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="fw-semibold"><i class="bi bi-book me-1"></i>Daftar Pelajaran<?= $aktif ? ' · ' . esc($aktif['name']) : '' ?></span>
                <?php if ($aktif): ?>
                    <button class="btn btn-outline-primary btn-sm ms-auto" type="button" data-bs-toggle="modal" data-bs-target="#mapelModal">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Pelajaran
                    </button>
                <?php endif ?>
            </div>
            <?php if (! $aktif): ?>
                <div class="card-body text-muted">Pilih ujian di sebelah kiri untuk melihat pelajarannya.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Nama Pelajaran</th><th>Shortname</th><th class="text-center">Status</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                        <?php if ($mapel === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada pelajaran.</td></tr>
                        <?php endif ?>
                        <?php foreach ($mapel as $c): ?>
                            <tr>
                                <td><?= esc($c['fullname']) ?></td>
                                <td><code><?= esc($c['shortname']) ?></code></td>
                                <td class="text-center"><?= (int) $c['visible'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-link" href="<?= esc($moodle . '/course/view.php?id=' . (int) $c['id'], 'attr') ?>" target="_blank" rel="noopener" title="Buka di Moodle untuk mengisi soal">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body small text-muted border-top">
                    Pelajaran baru dibuat sebagai course <em>Single activity</em> berisi Quiz dan tersembunyi dari peserta.
                    Isi soalnya di Moodle (ikon <i class="bi bi-box-arrow-up-right"></i>), lalu aktifkan di <a href="<?= site_url('status_tes') ?>?kat=<?= (int) $aktif['id'] ?>">Status Test</a>.
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<div class="modal fade" id="ujianModal" tabindex="-1" aria-labelledby="ujianModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= site_url('tools/test_manage/ujian') ?>">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title" id="ujianModalLabel">Tambah Daftar Ujian</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <label class="form-label" for="u_nama">Nama ujian</label>
                <input class="form-control" id="u_nama" name="nama" maxlength="255" required placeholder="Masukkan nama ujian">
                <p class="small text-muted mt-2 mb-0">Tips: gunakan nama kegiatan dan hari ujian, mis. PTS-1, PTS-2, PAS-1-1, PAS-1-2, H1, H2.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </div>
        </form>
    </div>
</div>

<?php if ($aktif): ?>
<div class="modal fade" id="mapelModal" tabindex="-1" aria-labelledby="mapelModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="<?= site_url('tools/test_manage/mapel') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="kategori" value="<?= (int) $aktif['id'] ?>">
            <div class="modal-header"><h5 class="modal-title" id="mapelModalLabel">Tambah Pelajaran · <?= esc($aktif['name']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <label class="form-label" for="m_full">Nama pelajaran</label>
                <input class="form-control" id="m_full" name="fullname" maxlength="254" required placeholder="mis. MATEMATIKA X">
                <label class="form-label mt-3" for="m_short">Shortname</label>
                <input class="form-control" id="m_short" name="shortname" maxlength="100" required placeholder="mis. USBK-X-MATEMATIKA">
                <p class="small text-muted mt-2 mb-0">Shortname harus unik (belum pernah dipakai pelajaran lain).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah</button>
            </div>
        </form>
    </div>
</div>
<?php endif ?>
<?= $this->endSection() ?>
