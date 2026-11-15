<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php $query = http_build_query(array_filter(['quiz' => $quizId ?: '', 'ruang' => $ruangId, 'kelas' => $kelas, 'tingkat' => $tingkat], static fn ($v) => $v !== '')); ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= site_url('noattempt_check') ?>">
            <div class="flex-grow-1" style="min-width: 240px">
                <label class="form-label small mb-1" for="fQuiz">Quiz</label>
                <select class="form-select form-select-sm" id="fQuiz" name="quiz" required>
                    <option value="">Pilih quiz</option>
                    <?php foreach ($quizzes as $q): ?>
                        <option value="<?= (int) $q['id'] ?>" <?= (int) $q['id'] === $quizId ? 'selected' : '' ?>><?= esc($q['course_short'] . ' · ' . $q['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?= view('partials/filter_tingkat', ['tingkat' => $tingkat, 'autoSubmit' => false]) ?>
            <div>
                <label class="form-label small mb-1" for="fKelas">Kelas</label>
                <select class="form-select form-select-sm" id="fKelas" name="kelas">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= esc($k, 'attr') ?>" <?= $k === $kelas ? 'selected' : '' ?>><?= esc($k) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="fRuang">Ruang</label>
                <select class="form-select form-select-sm" id="fRuang" name="ruang">
                    <option value="">Semua ruang</option>
                    <option value="0" <?= $ruangId === '0' ? 'selected' : '' ?>>(tanpa ruang)</option>
                    <?php foreach ($ruangList as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" data-kelas="<?= esc((string) $r['kelas'], 'attr') ?>" <?= (string) $r['id'] === $ruangId ? 'selected' : '' ?>><?= esc($r['nama']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Periksa</button>
        </form>
    </div>
</div>

<?php if ($quiz): ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold"><?= esc($quiz['name']) ?></span>
            <span class="small text-muted">
                <?= count($rows) ?> dari <?= (int) $terdaftar ?> peserta terdaftar belum punya attempt
            </span>
            <a class="btn btn-outline-secondary btn-sm ms-auto" href="<?= site_url('noattempt_check/export') . '?' . $query ?>"><i class="bi bi-download me-1"></i>Export Excel</a>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle w-100 datatable" data-order-col="4">
                <thead><tr><th>No. Peserta</th><th>Username</th><th>Nama</th><th>Kelas</th><th>Ruang</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= esc($r['nomor_peserta']) ?></td>
                        <td><code><?= esc($r['username']) ?></code></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td><?= esc($r['kelas']) ?></td>
                        <td><?= esc($r['ruang'] ?? '-') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">Peserta terdaftar = user yang terdaftar aktif di kursus quiz ini. Kelas dan ruang diambil dari Data CBTAdmin.</p>
<?php else: ?>
    <div class="card"><div class="card-body text-muted">Pilih quiz untuk melihat peserta yang belum mengerjakan sama sekali.</div></div>
<?php endif ?>
<?= $this->endSection() ?>
