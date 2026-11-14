<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<?= view('pdf/_style') ?>
<style>
    @page { margin: 10mm; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 4mm 4mm; }
    table.grid > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
    .id { border: 1px dashed #555; padding: 3mm 4mm; height: 52mm; }
    .id .ruang { font-size: 13pt; font-weight: bold; }
    .id .lokasi { font-size: 9pt; color: #444; margin-bottom: 2mm; }
    .id table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
    .id td { padding: 0.8mm 0; vertical-align: top; }
    .id .kode { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; font-size: 11pt; }
    .id .url { font-size: 8pt; color: #333; margin-top: 2mm; }
</style>
</head>
<body>
<h3 style="margin:0 4mm">ID Login Pengawas Ruang · <?= esc($school['nama_kegiatan'] !== '' ? $school['nama_kegiatan'] : 'Ujian Berbasis Komputer') ?></h3>
<div style="margin:0 4mm 1mm; font-size:9pt"><?= esc($school['nama_sekolah']) ?></div>
<?php foreach (array_chunk($rows, 8) as $p => $page): ?>
    <?php if ($p > 0): ?><div class="break"></div><?php endif ?>
    <table class="grid">
        <?php foreach (array_chunk($page, 2) as $pair): ?>
            <tr>
                <?php foreach ($pair as $r): ?>
                    <td>
                        <div class="id">
                            <div class="ruang">RUANG <?= esc($r['nama']) ?></div>
                            <div class="lokasi"><?= esc($r['lokasi'] !== '' ? $r['lokasi'] : ' ') ?><?= $r['sesi_nama'] ? ' · ' . esc($r['sesi_nama']) : '' ?> · <?= (int) $r['jumlah_peserta'] ?> peserta</div>
                            <table>
                                <tr><td style="width:24mm">Kode Login</td><td class="kode">: <?= esc($r['username']) ?></td></tr>
                                <tr><td>Password</td><td class="kode">: <?= esc((string) $r['password_plain']) ?></td></tr>
                                <tr><td>Pengawas</td><td>: <?= esc($r['pengawas'] !== '' ? $r['pengawas'] : '................................') ?></td></tr>
                            </table>
                            <div class="url">Login di: <?= esc($url) ?></div>
                        </div>
                    </td>
                <?php endforeach ?>
                <?php if (count($pair) === 1): ?><td></td><?php endif ?>
            </tr>
        <?php endforeach ?>
    </table>
<?php endforeach ?>
</body></html>
