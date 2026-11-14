<?php
use App\Libraries\School;

$tanggal = School::tanggal(time(), false);
// Tiap ruang/kelas dimulai di halaman baru; 8 kartu per halaman dengan label kelompok di atasnya.
$pages = [];
foreach ($groups as $label => $rows) {
    foreach (array_chunk($rows, 8) as $i => $chunk) {
        $pages[] = ['label' => $label, 'part' => $i + 1, 'parts' => (int) ceil(count($rows) / 8), 'count' => count($rows), 'rows' => $chunk];
    }
}
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8">
<?= view('pdf/_style') ?>
<style>
    @page { margin: 8mm; }
    table.grid { width: 100%; border-collapse: separate; border-spacing: 3mm 2.5mm; }
    table.grid > tbody > tr > td { width: 50%; vertical-align: top; padding: 0; }
    .kartu { border: 1px solid #333; height: 58mm; padding: 1.8mm 2.5mm; font-size: 7.2pt; line-height: 1.25; }
    .kartu table { width: 100%; border-collapse: collapse; }
    .kartu .head { border-bottom: 1px solid #333; margin-bottom: 1.2mm; }
    .kartu .head td { vertical-align: middle; padding-bottom: 1mm; }
    .kartu .head img { height: 9mm; width: auto; }
    .kartu .judul { font-weight: bold; font-size: 8.5pt; }
    .kartu .kecil { font-size: 6.3pt; }
    .kartu .isi td { padding: 0.2mm 1mm 0.2mm 0; vertical-align: top; }
    .kartu .nw { white-space: nowrap; }
    .kartu .akun { font-family: "DejaVu Sans Mono", monospace; font-weight: bold; font-size: 8.2pt; white-space: nowrap; }
    .kartu .ttd { font-size: 6.3pt; text-align: center; line-height: 1.2; }
    .kartu .ttd .space { height: 7mm; }
    .kelompok { font-size: 8pt; font-weight: bold; margin: 0 3mm; border-bottom: 1px solid #999; padding-bottom: 0.5mm; }
    .kelompok span { font-weight: normal; color: #555; }
</style>
</head>
<body>
<?php foreach ($pages as $p => $page): ?>
    <div class="kelompok">
        <?= esc($page['label']) ?>
        <span>· <?= (int) $page['count'] ?> peserta<?= $page['parts'] > 1 ? ' · halaman ' . $page['part'] . '/' . $page['parts'] : '' ?></span>
    </div>
    <table class="grid">
        <?php foreach (array_chunk($page['rows'], 2) as $pair): ?>
            <tr>
                <?php foreach ($pair as $r): ?>
                    <td>
                        <div class="kartu">
                            <table class="head">
                                <tr>
                                    <?php if ($school['logo']): ?><td style="width:12mm"><img src="<?= $school['logo'] ?>" alt=""></td><?php endif ?>
                                    <td>
                                        <div class="judul">KARTU PESERTA</div>
                                        <div class="kecil"><?= esc($school['nama_kegiatan'] !== '' ? $school['nama_kegiatan'] : 'Ujian Berbasis Komputer') ?></div>
                                        <div class="kecil"><?= esc($school['nama_sekolah']) ?></div>
                                    </td>
                                </tr>
                            </table>
                            <table class="isi">
                                <tr><td style="width:15mm" class="nw">No. Peserta</td><td>: <?= esc($r['nomor_peserta']) ?></td></tr>
                                <tr><td>Nama</td><td>: <strong><?= esc($r['nama']) ?></strong></td></tr>
                                <tr><td>Kelas</td><td>: <?= esc($r['kelas']) ?></td></tr>
                                <tr><td class="nw">Ruang/Sesi</td><td>: <?= esc($r['ruang_nama'] ?? '-') ?><?= $r['sesi_nama'] ? ' / ' . esc($r['sesi_nama']) : '' ?></td></tr>
                            </table>
                            <table style="margin-top:1mm">
                                <tr>
                                    <td style="width:52%; vertical-align:top">
                                        <table class="isi">
                                            <tr><td style="width:15mm" class="nw">Username</td><td class="akun">:&nbsp;<?= esc($r['username']) ?></td></tr>
                                            <tr><td class="nw">Password</td><td class="akun">:&nbsp;<?= esc((string) $r['password_plain']) ?></td></tr>
                                        </table>
                                    </td>
                                    <td class="ttd" style="vertical-align:top">
                                        <?= esc($school['kota'] !== '' ? $school['kota'] . ', ' : '') . $tanggal ?><br>Kepala Sekolah
                                        <div class="space"></div>
                                        <strong><?= esc($school['kepala_sekolah'] !== '' ? $school['kepala_sekolah'] : '........................') ?></strong><br>
                                        <?= $school['nip_kepala'] !== '' ? 'NIP ' . esc($school['nip_kepala']) : '' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                <?php endforeach ?>
                <?php if (count($pair) === 1): ?><td></td><?php endif ?>
            </tr>
        <?php endforeach ?>
    </table>
    <?php if ($p < count($pages) - 1): ?><div class="break"></div><?php endif ?>
<?php endforeach ?>
</body></html>
