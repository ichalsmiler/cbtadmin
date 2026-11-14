<table class="kop">
    <tr>
        <?php if ($school['logo']): ?>
            <td class="logo"><img src="<?= $school['logo'] ?>" alt=""></td>
        <?php endif ?>
        <td>
            <div class="nama"><?= esc($school['nama_sekolah'] !== '' ? $school['nama_sekolah'] : 'Nama sekolah belum diatur') ?></div>
            <div class="sub">
                <?= esc($school['alamat']) ?>
                <?= $school['npsn'] !== '' ? ' · NPSN ' . esc($school['npsn']) : '' ?>
            </div>
            <?php if ($school['nama_kegiatan'] !== ''): ?>
                <div class="sub"><strong><?= esc($school['nama_kegiatan']) ?></strong></div>
            <?php endif ?>
        </td>
    </tr>
</table>
