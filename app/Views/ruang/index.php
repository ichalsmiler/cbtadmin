<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="fw-semibold">Ruang</span>
                <button class="btn btn-primary btn-sm ms-auto" type="button" data-ruang='{"id":"","nama":"","kapasitas":"","pengawas":"","sesi_id":""}'>
                    <i class="bi bi-plus-lg me-1"></i>Tambah Ruang
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Nama</th><th>Sesi</th><th>Pengawas</th><th class="text-end">Peserta / Kapasitas</th><th></th></tr></thead>
                    <tbody>
                    <?php if ($ruang === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada ruang.</td></tr>
                    <?php endif ?>
                    <?php foreach ($ruang as $r): ?>
                        <tr>
                            <td><?= esc($r['nama']) ?></td>
                            <td><?= esc($r['sesi_nama'] ?? '-') ?></td>
                            <td><?= esc($r['pengawas'] !== '' ? $r['pengawas'] : '-') ?></td>
                            <td class="text-end"><?= (int) $r['jumlah_peserta'] ?> / <?= (int) $r['kapasitas'] ?></td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-outline-secondary btn-sm" type="button" title="Ubah"
                                        data-ruang="<?= esc(json_encode(['id' => $r['id'], 'nama' => $r['nama'], 'kapasitas' => $r['kapasitas'], 'pengawas' => $r['pengawas'], 'sesi_id' => $r['sesi_id'] ?? '']), 'attr') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form class="d-inline" method="post" action="<?= site_url('daftar_ruang/hapus/' . $r['id']) ?>"
                                      data-confirm="Hapus ruang &quot;<?= esc($r['nama'], 'attr') ?>&quot;? Peserta di ruang ini menjadi tanpa ruang.">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-outline-danger btn-sm" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header fw-semibold">Sesi</div>
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
                        <form class="ms-auto" method="post" action="<?= site_url('daftar_ruang/sesi/hapus/' . $s['id']) ?>"
                              data-confirm="Hapus sesi &quot;<?= esc($s['nama'], 'attr') ?>&quot;?">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline-danger btn-sm" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </li>
                <?php endforeach ?>
            </ul>
            <div class="card-body border-top">
                <form method="post" action="<?= site_url('daftar_ruang/sesi/simpan') ?>" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12"><input class="form-control form-control-sm" name="nama" placeholder="Nama sesi, mis. Sesi 1" maxlength="60" required></div>
                    <div class="col-6"><input class="form-control form-control-sm" type="time" name="jam_mulai" aria-label="Jam mulai"></div>
                    <div class="col-6"><input class="form-control form-control-sm" type="time" name="jam_selesai" aria-label="Jam selesai"></div>
                    <div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Tambah Sesi</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ruangModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('daftar_ruang/simpan') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title">Ruang</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nama ruang</label><input class="form-control" name="nama" maxlength="60" required></div>
                <div class="mb-3"><label class="form-label">Kapasitas</label><input class="form-control" type="number" name="kapasitas" min="0"></div>
                <div class="mb-3"><label class="form-label">Pengawas</label><input class="form-control" name="pengawas" maxlength="120"></div>
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
<?= $this->endSection() ?>
