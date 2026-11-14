<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$query = http_build_query(array_filter(['quiz' => $quizId ?: '', 'ruang' => $ruangId, 'kelas' => $kelas, 'tingkat' => $tingkat], static fn ($v) => $v !== ''));
$num   = static fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '.');
$badge = ['finished' => 'success', 'inprogress' => 'primary', 'notstarted' => 'secondary'];
?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>

<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= site_url('hasil_ujian') ?>">
            <div class="flex-grow-1" style="min-width: 240px">
                <label class="form-label small mb-1" for="fQuiz">Quiz</label>
                <select class="form-select form-select-sm" id="fQuiz" name="quiz" required>
                    <option value="">Pilih quiz</option>
                    <?php foreach ($quizzes as $q): ?>
                        <option value="<?= (int) $q['id'] ?>" <?= (int) $q['id'] === $quizId ? 'selected' : '' ?>><?= esc($q['course_short'] . ' · ' . $q['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
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
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $ruangId ? 'selected' : '' ?>><?= esc($r['nama']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?= view('partials/filter_tingkat', ['tingkat' => $tingkat, 'autoSubmit' => false]) ?>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Tampilkan</button>
        </form>
    </div>
</div>

<?php if ($quiz && $stats): ?>
    <div class="row g-3 mb-3">
        <?php foreach ([
            ['Selesai', $stats['selesai'] . ' / ' . $stats['peserta']],
            ['Rata-rata', $num($stats['rata'])],
            ['Terendah', $num($stats['min'])],
            ['Tertinggi', $num($stats['maks'])],
        ] as [$label, $value]): ?>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-value fs-3"><?= $value ?></div><div class="stat-label"><?= $label ?></div></div>
            </div>
        <?php endforeach ?>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="fw-semibold"><?= esc($quiz['name']) ?></span>
            <span class="small text-muted">Nilai skala 0–<?= $num($stats['skala']) ?></span>
            <div class="ms-auto d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('hasil_ujian/export') . '?' . $query ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('hasil_ujian/pdf') . '?' . $query ?>" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle w-100 datatable" data-order-col="2">
                <thead><tr><th>No. Peserta</th><th>Username</th><th>Nama</th><th>Kelas</th><th>Ruang</th><th>Status</th><th class="text-end">Nilai</th><th>Mulai</th><th>Selesai</th><th>Durasi</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= esc($r['nomor_peserta']) ?></td>
                        <td><code><?= esc($r['username']) ?></code></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td><?= esc($r['kelas']) ?></td>
                        <td><?= esc($r['ruang'] ?? '-') ?></td>
                        <td><span class="badge text-bg-<?= $badge[$r['status']] ?>"><?= \App\Controllers\HasilUjian::label($r['status']) ?></span></td>
                        <td class="text-end fw-semibold" data-order="<?= $r['nilai'] ?? -1 ?>"><?= $num($r['nilai']) ?></td>
                        <td data-order="<?= $r['timestart'] ?>"><?= $r['timestart'] ? date('d/m H:i', $r['timestart']) : '–' ?></td>
                        <td data-order="<?= $r['timefinish'] ?>"><?= $r['timefinish'] ? date('d/m H:i', $r['timefinish']) : '–' ?></td>
                        <td data-order="<?= $r['durasi'] ?? -1 ?>"><?= $r['durasi'] !== null ? \App\Controllers\HasilUjian::durasi($r['durasi']) : '–' ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">Nilai diambil dari nilai akhir quiz di Moodle (sesuai metode penilaian quiz). Peserta yang sedang mengerjakan belum punya nilai.</p>
<?php elseif (! $quiz): ?>
    <div class="card"><div class="card-body text-muted">Pilih quiz untuk melihat nilai peserta.</div></div>
<?php endif ?>
<?= $this->endSection() ?>
