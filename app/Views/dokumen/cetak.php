<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">Cetak kartu peserta atau daftar hadir</div>
            <div class="card-body">
                <form method="get" target="_blank" id="cetakForm">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="fRuang">Ruang</label>
                            <select class="form-select" id="fRuang" name="ruang">
                                <option value="">Semua ruang (<?= (int) $total ?> peserta)</option>
                                <option value="0">(tanpa ruang)</option>
                                <?php foreach ($ruangList as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>"><?= esc($r['nama']) ?> (<?= (int) $r['jumlah'] ?> peserta)</option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="fSesi">Sesi</label>
                            <select class="form-select" id="fSesi" name="sesi">
                                <option value="">Semua sesi</option>
                                <?php foreach ($sesiList as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= esc($s['nama']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="fQuiz">Mata pelajaran untuk daftar hadir (opsional)</label>
                            <select class="form-select" id="fQuiz" name="quiz">
                                <option value="">(dikosongkan, diisi tangan)</option>
                                <?php foreach ($quizzes as $q): ?>
                                    <option value="<?= (int) $q['id'] ?>"><?= esc($q['course_short'] . ' · ' . $q['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button class="btn btn-primary" type="submit" formaction="<?= site_url('dokumen/cetak_doc/kartu') ?>"><i class="bi bi-person-vcard me-1"></i>Cetak Kartu Peserta</button>
                        <button class="btn btn-outline-primary" type="submit" formaction="<?= site_url('dokumen/cetak_doc/daftar_hadir') ?>"><i class="bi bi-list-check me-1"></i>Cetak Daftar Hadir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body small text-muted">
                <p class="mb-2"><strong>Kartu peserta</strong>: 8 kartu per halaman A4, berisi nama, nomor peserta, username, password, ruang, sesi, nama kegiatan, logo, dan tanda tangan kepala sekolah.</p>
                <p class="mb-2"><strong>Daftar hadir</strong>: satu halaman per ruang dan sesi, dengan kolom tanda tangan dan tempat tanda tangan pengawas.</p>
                <p class="mb-0">Nama kegiatan, identitas sekolah, logo, dan kepala sekolah diatur di <a href="<?= site_url('tools') ?>">Tools</a>. PDF terbuka di tab baru; cetak dari sana.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
