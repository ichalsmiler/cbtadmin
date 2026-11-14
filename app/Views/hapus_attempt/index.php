<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$badges = ['inprogress' => ['primary', 'Sedang Mengerjakan'], 'finished' => ['success', 'Selesai']];
$fmt    = static fn ($ts) => $ts > 0 ? date('d/m H:i', $ts) : '–';
?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>

<div class="alert alert-warning d-flex gap-2" role="note">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        Setiap quiz hanya bisa dikerjakan <strong>1 kali</strong>. Menghapus attempt menghilangkan jawaban dan nilai peserta
        <strong>secara permanen</strong>, lalu peserta bisa mengerjakan ulang dari awal. Pakai hanya untuk kasus khusus
        (mis. salah quiz, kendala teknis yang membuat jawaban tidak tersimpan). Setiap penghapusan dicatat di Log Aktivitas beserta alasannya.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= site_url('hapus_attempt') ?>">
            <div class="flex-grow-1" style="min-width: 240px">
                <label class="form-label small mb-1" for="fQuiz">Quiz</label>
                <select class="form-select form-select-sm" id="fQuiz" name="quiz" onchange="this.form.submit()">
                    <option value="">Semua quiz</option>
                    <?php foreach ($quizzes as $q): ?>
                        <option value="<?= (int) $q['id'] ?>" <?= $quiz && (int) $quiz['id'] === (int) $q['id'] ? 'selected' : '' ?>>
                            <?= esc($q['course_short'] . ' · ' . $q['name']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="fRuang">Ruang</label>
                <select class="form-select form-select-sm" id="fRuang" name="ruang" onchange="this.form.submit()">
                    <option value="">Semua ruang</option>
                    <option value="0" <?= $ruangId === '0' ? 'selected' : '' ?>>(tanpa ruang)</option>
                    <?php foreach ($ruangList as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) $r['id'] === $ruangId ? 'selected' : '' ?>><?= esc($r['nama']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label class="form-label small mb-1" for="fKelas">Kelas</label>
                <select class="form-select form-select-sm" id="fKelas" name="kelas" onchange="this.form.submit()">
                    <option value="">Semua kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= esc($k, 'attr') ?>" <?= $k === $kelas ? 'selected' : '' ?>><?= esc($k) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <form method="post" action="<?= site_url('hapus_attempt/hapus') ?>" data-bulk>
        <?= csrf_field() ?>
        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted me-1">Untuk yang dicentang:</span>
            <input class="form-control form-control-sm" style="max-width: 22rem" type="text" name="alasan" required minlength="3" maxlength="200"
                   placeholder="Alasan (wajib), mis. komputer mati dan jawaban hilang" aria-label="Alasan menghapus attempt">
            <button class="btn btn-danger btn-sm" type="submit"
                    data-confirm-button="Hapus attempt peserta yang dicentang? Jawaban dan nilainya hilang permanen dan tidak bisa dikembalikan. Peserta lalu bisa mengerjakan ulang.">
                <i class="bi bi-trash me-1"></i>Hapus attempt
            </button>
        </div>
        <div class="card-body">
            <table class="table table-hover align-middle w-100 datatable" data-order-col="2">
                <thead>
                <tr>
                    <th data-orderable="false" style="width:1%"><input class="form-check-input" type="checkbox" data-check-all aria-label="Centang semua"></th>
                    <th>Username</th><th>Nama</th><th>Kelas</th><th>Ruang</th>
                    <?php if (! $quiz): ?><th>Quiz</th><?php endif ?>
                    <th>Status</th><th>Mulai</th><th>Selesai</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php [$cls, $label] = $badges[$r['status']]; ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= esc($r['key'], 'attr') ?>" aria-label="Pilih <?= esc($r['username'], 'attr') ?>"></td>
                        <td><code><?= esc($r['username']) ?></code></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td><?= esc($r['kelas']) ?></td>
                        <td><?= esc($r['ruang'] ?? '-') ?></td>
                        <?php if (! $quiz): ?><td><?= esc($r['quiz_name']) ?></td><?php endif ?>
                        <td><span class="badge text-bg-<?= $cls ?>"><?= $label ?></span></td>
                        <td data-order="<?= $r['timestart'] ?>"><?= $fmt($r['timestart']) ?></td>
                        <td data-order="<?= $r['timefinish'] ?>"><?= $fmt($r['timefinish']) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
