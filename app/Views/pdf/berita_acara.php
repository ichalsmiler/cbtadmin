<?php
use App\Libraries\School;

$hari = (int) $quiz['timeopen'] > 0 ? School::tanggal((int) $quiz['timeopen']) : '..............................';
$last = count($ruang) - 1;
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><?= view('pdf/_style') ?>
<style>
    p.isi { line-height: 1.6; text-align: justify; }
    .catatan { border: 1px solid #333; height: 26mm; margin-top: 4px; }
</style>
</head>
<body>
<?php if ($ruang === []): ?>
    <?= view('pdf/_kop', ['school' => $school]) ?>
    <p>Tidak ada peserta terdaftar untuk quiz ini.</p>
<?php endif ?>
<?php foreach ($ruang as $i => $g): ?>
    <?= view('pdf/_kop', ['school' => $school]) ?>
    <h2>BERITA ACARA PELAKSANAAN UJIAN</h2>
    <p class="isi">
        Pada hari ini <?= esc($hari) ?>, telah dilaksanakan
        <?= esc($school['nama_kegiatan'] !== '' ? $school['nama_kegiatan'] : 'ujian berbasis komputer') ?>
        untuk mata pelajaran <strong><?= esc($quiz['name']) ?></strong> dengan rincian sebagai berikut:
    </p>
    <table class="meta">
        <tr><td>Ruang</td><td>: <?= esc($g['nama']) ?></td></tr>
        <tr><td>Sesi / waktu</td><td>: <?= esc($g['sesi'] !== '' ? $g['sesi'] : '........') ?><?= $g['jam'] !== '' ? ' (' . esc($g['jam']) . ')' : '' ?></td></tr>
        <tr><td>Jumlah peserta seharusnya</td><td>: <?= $g['total'] ?> orang</td></tr>
        <tr><td>Jumlah hadir</td><td>: <?= $g['hadir'] ?> orang</td></tr>
        <tr><td>Jumlah tidak hadir</td><td>: <?= count($g['absen']) ?> orang</td></tr>
    </table>

    <?php if ($g['absen'] !== []): ?>
        <p style="margin:8px 0 0">Peserta tidak hadir:</p>
        <table class="data">
            <thead><tr><th style="width:8mm">No</th><th style="width:30mm">No. Peserta</th><th>Nama</th><th style="width:30mm">Kelas</th></tr></thead>
            <tbody>
            <?php foreach ($g['absen'] as $n => $r): ?>
                <tr><td class="center"><?= $n + 1 ?></td><td><?= esc($r['nomor_peserta']) ?></td><td><?= esc($r['nama']) ?></td><td><?= esc($r['kelas']) ?></td></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <p style="margin:10px 0 0">Catatan selama pelaksanaan ujian:</p>
    <div class="catatan"></div>

    <table class="ttd">
        <tr>
            <td>
                Mengetahui,<br><?= esc($school['jabatan']) ?>
                <?php if ($school['ttd']): ?>
                    <div class="space"><img src="<?= $school['ttd'] ?>" alt="" style="max-height:100%; width:auto"></div>
                <?php else: ?>
                    <div class="space"></div>
                <?php endif ?>
                <strong><?= esc($school['kepala_sekolah'] !== '' ? $school['kepala_sekolah'] : '..............................') ?></strong><br>
                <?= esc($school['jenis_nomor']) ?> <?= esc($school['nip_kepala'] !== '' ? $school['nip_kepala'] : '..............................') ?>
            </td>
            <td>
                <?= esc($school['kota'] !== '' ? $school['kota'] . ', ' : '') ?><?= (int) $quiz['timeopen'] > 0 ? School::tanggal((int) $quiz['timeopen'], false) : '..............................' ?><br>
                Pengawas Ruang
                <div class="space"></div>
                <strong><?= esc($g['pengawas'] !== '' ? $g['pengawas'] : '..............................') ?></strong><br>
                NIP ..............................
            </td>
        </tr>
    </table>
    <?php if ($i < $last): ?><div class="break"></div><?php endif ?>
<?php endforeach ?>
</body></html>
