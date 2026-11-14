<?php
use App\Controllers\HasilUjian;
use App\Libraries\School;

$num = static fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><?= view('pdf/_style') ?></head>
<body>
<?= view('pdf/_kop', ['school' => $school]) ?>
<h2>REKAP HASIL UJIAN</h2>
<table class="meta">
    <tr><td>Mata pelajaran</td><td>: <?= esc($quiz['name']) ?> (<?= esc($quiz['course_short']) ?>)</td></tr>
    <tr><td>Peserta selesai</td><td>: <?= (int) $stats['selesai'] ?> dari <?= (int) $stats['peserta'] ?></td></tr>
    <tr><td>Rata-rata / terendah / tertinggi</td><td>: <?= $num($stats['rata']) ?> / <?= $num($stats['min']) ?> / <?= $num($stats['maks']) ?> (skala 0–<?= $num($stats['skala']) ?>)</td></tr>
</table>
<table class="data">
    <thead><tr><th>No</th><th>No. Peserta</th><th>Nama</th><th>Kelas</th><th>Ruang</th><th>Status</th><th>Nilai</th><th>Durasi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
        <tr>
            <td class="center"><?= $i + 1 ?></td>
            <td><?= esc($r['nomor_peserta']) ?></td>
            <td><?= esc($r['nama']) ?></td>
            <td><?= esc($r['kelas']) ?></td>
            <td><?= esc($r['ruang'] ?? '') ?></td>
            <td><?= HasilUjian::label($r['status']) ?></td>
            <td class="right"><?= $num($r['nilai']) ?></td>
            <td class="center"><?= $r['durasi'] !== null ? HasilUjian::durasi($r['durasi']) : '–' ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<p class="muted" style="margin-top:6px">Dicetak <?= School::tanggal(time()) ?> pukul <?= date('H:i') ?>.</p>
</body></html>
