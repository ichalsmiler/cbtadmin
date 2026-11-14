<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$tgl = static fn (string $d): string => date('d/m/Y', strtotime($d));
$sudah = count(array_filter($rows, static fn ($r) => $r['status'] !== null));
?>
<?php if (! $ruang): ?>
    <div class="alert alert-warning">Akun ini belum terhubung ke ruang. Hubungi administrator.</div>
<?php else: ?>
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span class="fw-semibold">Ruang <?= esc($ruang['nama']) ?> · <?= $tgl($tanggal) ?></span>
        <span class="text-muted small"><?= count($rows) ?> siswa · <?= $sudah ?> sudah diabsen</span>
        <?php if ($isAdmin): ?>
            <a class="ms-auto small" href="<?= site_url('dokumen/absensi') ?>?tanggal=<?= esc($tanggal, 'url') ?>">&laquo; Rekap semua ruang</a>
        <?php endif ?>
    </div>
    <div class="card-body">
        <?php if ($rows === []): ?>
            <p class="text-muted mb-0">Belum ada siswa di ruang ini.</p>
        <?php else: ?>
            <p class="small text-muted">
                Tandai setiap siswa lalu klik <strong>Simpan</strong>. Siswa yang <strong>tidak hadir</strong> langsung diblokir
                di Moodle sampai hari ini berakhir; bila ternyata datang, ubah menjadi Hadir dan simpan lagi.
            </p>
            <form method="post" action="<?= site_url('kehadiran/simpan') ?>" id="absenForm">
                <?= csrf_field() ?>
                <?php if ($isAdmin): ?>
                    <input type="hidden" name="ruang_id" value="<?= (int) $ruang['id'] ?>">
                    <input type="hidden" name="tanggal" value="<?= esc($tanggal, 'attr') ?>">
                <?php endif ?>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <button class="btn btn-outline-success btn-sm" type="button" id="semuaHadir"><i class="bi bi-check2-all me-1"></i>Tandai semua hadir</button>
                    <span class="small text-muted align-self-center" id="ringkas"></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>No. Peserta</th><th>Nama</th><th>Kelas</th><th class="text-center">Kehadiran</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php $pid = (int) $r['id']; ?>
                            <tr>
                                <td><?= esc($r['nomor_peserta'] ?: '–') ?></td>
                                <td><?= esc($r['nama']) ?><div class="small text-muted"><?= esc($r['username']) ?></div></td>
                                <td><?= esc($r['kelas']) ?></td>
                                <td class="text-center text-nowrap">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Kehadiran <?= esc($r['nama'], 'attr') ?>">
                                        <input type="radio" class="btn-check" name="status[<?= $pid ?>]" id="h<?= $pid ?>" value="hadir" <?= $r['status'] === 'hadir' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success" for="h<?= $pid ?>">Hadir</label>
                                        <input type="radio" class="btn-check" name="status[<?= $pid ?>]" id="t<?= $pid ?>" value="tidak_hadir" <?= $r['status'] === 'tidak_hadir' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger" for="t<?= $pid ?>">Tidak hadir</label>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ((int) $r['diblokir']): ?>
                                        <span class="badge text-bg-danger">Diblokir</span>
                                        <?php if ($isAdmin): ?>
                                            <button class="btn btn-link btn-sm p-0 ms-1" type="button"
                                                    data-post="<?= site_url('dokumen/absensi/buka') ?>"
                                                    data-fields="<?= esc(json_encode(['peserta_id' => $pid, 'tanggal' => $tanggal]), 'attr') ?>"
                                                    data-confirm-text="Buka blokir <?= esc($r['nama'], 'attr') ?>? Status tetap tidak hadir.">Buka blokir</button>
                                        <?php endif ?>
                                    <?php elseif ($r['status'] === null): ?>
                                        <span class="badge text-bg-light">Belum diabsen</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
            </form>
        <?php endif ?>
    </div>
</div>
<form method="post" id="postForm" class="d-none"><?= csrf_field() ?></form>
<?php endif ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';
    var form = document.getElementById('absenForm');
    if (!form) { return; }
    var radios = form.querySelectorAll('input[type=radio]');
    var update = function () {
        var h = form.querySelectorAll('input[value=hadir]:checked').length;
        var t = form.querySelectorAll('input[value=tidak_hadir]:checked').length;
        var total = form.querySelectorAll('input[value=hadir]').length;
        document.getElementById('ringkas').textContent = h + ' hadir · ' + t + ' tidak hadir · ' + (total - h - t) + ' belum ditandai';
    };
    radios.forEach(function (r) { r.addEventListener('change', update); });
    document.getElementById('semuaHadir').addEventListener('click', function () {
        form.querySelectorAll('input[value=hadir]').forEach(function (r) {
            var other = form.querySelector('input[name="' + r.name + '"][value=tidak_hadir]');
            if (!other.checked) { r.checked = true; }  // yang sudah ditandai tidak hadir dibiarkan
        });
        update();
    });
    form.addEventListener('submit', function (event) {
        var t = form.querySelectorAll('input[value=tidak_hadir]:checked').length;
        if (t > 0 && !form.dataset.ok) {
            event.preventDefault();
            var go = window.confirm(t + ' siswa ditandai TIDAK HADIR dan akan diblokir di Moodle hari ini. Lanjutkan?');
            if (go) { form.dataset.ok = '1'; form.submit(); }
        }
    });
    update();
})();
</script>
<?= $this->endSection() ?>
