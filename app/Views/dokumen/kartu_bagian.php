<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Kartu peserta per bagian</title>
<style>
    body { font-family: system-ui, sans-serif; font-size: 14px; color: #212529; background: #fff; margin: 0; padding: 20px; }
    p { margin: 0 0 12px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
    a { display: block; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; color: #0d6efd; text-decoration: none; }
    a:hover { background: #e7f1ff; border-color: #0d6efd; }
    small { color: #6c757d; }
</style></head>
<body>
<p><strong><?= (int) $total ?> kartu</strong> terlalu banyak untuk satu PDF (maksimal <?= (int) $maks ?>, agar server tidak kehabisan memori).
    Pilih bagian di bawah; PDF-nya tampil di sini, lalu Cetak atau Unduh.</p>
<div class="grid">
    <?php foreach ($links as $l): ?>
        <a href="<?= esc($l['url'], 'attr') ?>"><?= esc($l['label']) ?><br><small><?= (int) $l['jumlah'] ?> kartu</small></a>
    <?php endforeach ?>
</div>
</body></html>
