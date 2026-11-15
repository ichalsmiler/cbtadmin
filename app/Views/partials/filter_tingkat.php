<?php
/**
 * Pilihan Tingkatan (X/XI/XII) untuk formulir filter GET.
 * Variabel: $tingkat (nilai terpilih), $size ('sm' | ''), $autoSubmit (bool), $label (bool).
 */
$size       ??= 'sm';
$autoSubmit ??= true;
$label      ??= true;
?>
<div>
    <?php if ($label): ?><label class="form-label <?= $size === 'sm' ? 'small mb-1' : '' ?>" for="fTingkat">Tingkatan</label><?php endif ?>
    <select class="form-select <?= $size === 'sm' ? 'form-select-sm' : '' ?>" id="fTingkat" name="tingkat" aria-label="Tingkatan"
            <?= $autoSubmit ? 'onchange="this.form.submit()"' : '' ?>>
        <?php foreach (\App\Libraries\Tingkat::LIST as $t): ?>
            <option value="<?= $t ?>" <?= ($tingkat ?? '') === $t ? 'selected' : '' ?>>Kelas <?= $t ?></option>
        <?php endforeach ?>
        <option value="" <?= ($tingkat ?? '') === '' ? 'selected' : '' ?>>Semua tingkatan</option>
    </select>
</div>
