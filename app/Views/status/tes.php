<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<?php
$badge = [
    'buka'     => ['success', 'Dibuka'],
    'belum'    => ['info', 'Belum dibuka'],
    'tutup'    => ['secondary', 'Ditutup'],
    'sembunyi' => ['warning', 'Disembunyikan'],
];
$fmt = static fn ($ts) => (int) $ts > 0 ? date('d/m/Y H:i', (int) $ts) : '–';
$dtl = static fn ($ts) => (int) $ts > 0 ? date('Y-m-d\TH:i', (int) $ts) : '';
?>
<?php if ($moodleError): ?>
    <div class="alert alert-danger" role="alert">Database Moodle tidak bisa dibaca. Periksa pengaturan <code>moodle.*</code> di berkas <code>.env</code>.</div>
<?php endif ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover align-middle w-100 datatable" data-order-col="2">
            <thead>
            <tr>
                <th>Mapel / Quiz</th><th>Kursus</th><th>Buka</th><th>Tutup</th><th>Durasi</th><th>Token</th>
                <th class="text-center">Mulai</th><th class="text-center">Mengerjakan</th><th class="text-center">Selesai</th>
                <th>Status</th><th data-orderable="false"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $q): ?>
                <?php [$cls, $text] = $badge[$q['status']]; ?>
                <tr>
                    <td>
                        <a href="<?= esc(\App\Libraries\Moodle::url('/mod/quiz/view.php?id=' . (int) $q['cmid']), 'attr') ?>" target="_blank" rel="noopener"><?= esc($q['name']) ?></a>
                    </td>
                    <td><?= esc($q['course_short']) ?></td>
                    <td data-order="<?= (int) $q['timeopen'] ?>"><?= $fmt($q['timeopen']) ?></td>
                    <td data-order="<?= (int) $q['timeclose'] ?>"><?= $fmt($q['timeclose']) ?></td>
                    <td data-order="<?= (int) $q['timelimit'] ?>"><?= (int) $q['timelimit'] > 0 ? round($q['timelimit'] / 60) . ' menit' : '–' ?></td>
                    <td><?= $q['password'] !== '' ? '<code>' . esc($q['password']) . '</code>' : '–' ?></td>
                    <td class="text-center"><?= (int) $q['mulai'] ?></td>
                    <td class="text-center"><a href="<?= site_url('status_peserta/inprogress?quiz=' . (int) $q['id']) ?>"><?= (int) $q['sedang'] ?></a></td>
                    <td class="text-center"><a href="<?= site_url('status_peserta/finished?quiz=' . (int) $q['id']) ?>"><?= (int) $q['selesai'] ?></a></td>
                    <td><span class="badge text-bg-<?= $cls ?>"><?= $text ?></span></td>
                    <td class="text-end text-nowrap">
                        <?php if ($q['status'] !== 'buka'): ?>
                            <button class="btn btn-outline-success btn-sm" type="button" title="Buka sekarang"
                                    data-post="<?= site_url('status_tes/waktu') ?>" data-fields="<?= esc(json_encode(['quiz' => $q['id'], 'aksi' => 'buka']), 'attr') ?>"
                                    data-confirm-text="Buka quiz &quot;<?= esc($q['name'], 'attr') ?>&quot; sekarang?"><i class="bi bi-play-fill"></i></button>
                        <?php else: ?>
                            <button class="btn btn-outline-danger btn-sm" type="button" title="Tutup sekarang"
                                    data-post="<?= site_url('status_tes/waktu') ?>" data-fields="<?= esc(json_encode(['quiz' => $q['id'], 'aksi' => 'tutup']), 'attr') ?>"
                                    data-confirm-text="Tutup quiz &quot;<?= esc($q['name'], 'attr') ?>&quot; sekarang? Peserta yang masih mengerjakan akan disubmit otomatis oleh Moodle sesuai pengaturan quiz."><i class="bi bi-stop-fill"></i></button>
                        <?php endif ?>
                        <button class="btn btn-outline-secondary btn-sm" type="button" title="Atur waktu" data-fill-modal="#waktuModal"
                                data-fill="<?= esc(json_encode(['quiz' => $q['id'], 'nama' => $q['name'], 'timeopen' => $dtl($q['timeopen']), 'timeclose' => $dtl($q['timeclose'])]), 'attr') ?>">
                            <i class="bi bi-clock"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<form method="post" id="postForm" class="d-none"><?= csrf_field() ?></form>

<div class="modal fade" id="waktuModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= site_url('status_tes/waktu') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="quiz">
            <input type="hidden" name="aksi" value="atur">
            <div class="modal-header"><h5 class="modal-title">Atur waktu quiz</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Quiz</label><input class="form-control" name="nama" readonly></div>
                <div class="mb-3"><label class="form-label">Waktu buka</label><input class="form-control" type="datetime-local" name="timeopen"></div>
                <div><label class="form-label">Waktu tutup</label><input class="form-control" type="datetime-local" name="timeclose"></div>
                <p class="small text-muted mt-2 mb-0">Kosongkan untuk tanpa batas. Waktu mengikuti zona server (<?= esc(date('T')) ?>).</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
