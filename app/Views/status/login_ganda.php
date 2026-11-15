<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">Log Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>
<p><a class="small" href="<?= site_url('status_peserta') ?>">&laquo; Kembali ke Status Peserta</a></p>

<div class="alert alert-info d-flex gap-2" role="note">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        Akun peserta yang pada tanggal ini <strong>login dari lebih dari satu komputer</strong> (alamat IP berbeda).
        Bisa berarti akun dipakai orang lain, tetapi juga bisa karena siswa pindah komputer yang rusak.
        Periksa bersama pengawas ruang; bila perlu blokir atau hapus attempt-nya.
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex flex-wrap gap-2 align-items-end" method="get" action="<?= site_url('status_peserta/login_ganda') ?>">
            <div>
                <label class="form-label small mb-1" for="fTanggal">Tanggal</label>
                <input class="form-control form-control-sm" type="date" id="fTanggal" name="tanggal" value="<?= esc($tanggal, 'attr') ?>" onchange="this.form.submit()">
            </div>
            <?= view('partials/filter_tingkat', ['tingkat' => $tingkat]) ?>
            <div>
                <label class="form-label small mb-1" for="fRuang">Ruang</label>
                <select class="form-select form-select-sm" id="fRuang" name="ruang" onchange="this.form.submit()" <?= $lockRuang ? 'disabled' : '' ?>>
                    <?php if (! $lockRuang): ?><option value="">Semua ruang</option><?php endif ?>
                    <?php foreach ($ruangList as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" data-kelas="<?= esc((string) $r['kelas'], 'attr') ?>" <?= (string) $r['id'] === $ruangId ? 'selected' : '' ?>><?= esc($r['nama']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <span class="fw-semibold"><i class="bi bi-people me-1"></i>Login ganda <?= esc(date('d/m/Y', strtotime($tanggal))) ?></span>
        <span class="badge text-bg-<?= $rows ? 'danger' : 'success' ?> ms-auto"><?= count($rows) ?> akun</span>
    </div>
    <div class="card-body">
        <?php if ($rows === []): ?>
            <p class="text-muted mb-0">Tidak ada akun peserta yang login dari lebih dari satu komputer pada tanggal ini.</p>
        <?php else: ?>
            <table class="table align-middle w-100 datatable" data-order-col="4">
                <thead><tr><th>No. Peserta</th><th>Nama</th><th>Kelas</th><th>Ruang</th><th class="text-center">Komputer (IP)</th><th>Rincian login</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= esc($r['username']) ?></td>
                        <td><?= esc($r['nama']) ?></td>
                        <td><?= esc($r['kelas']) ?></td>
                        <td><?= esc($r['ruang_nama'] ?? '-') ?></td>
                        <td class="text-center" data-order="<?= -$r['jumlah_ip'] ?>"><span class="badge text-bg-danger"><?= (int) $r['jumlah_ip'] ?></span></td>
                        <td class="small">
                            <?php foreach ($r['ips'] as $ip): ?>
                                <div><code><?= esc($ip['ip']) ?></code> · <?= date('H:i', (int) $ip['pertama']) ?><?= (int) $ip['kali'] > 1 ? '–' . date('H:i', (int) $ip['terakhir']) . ' (' . (int) $ip['kali'] . '×)' : '' ?></div>
                            <?php endforeach ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
