<?= $this->extend('layout/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body text-center py-5">
        <div class="text-muted mb-2">
            <?= $testName !== '' ? 'Token tes <strong>' . esc($testName) . '</strong>' : 'Belum ada tes aktif' ?>
        </div>
        <div class="token-display font-monospace fw-bold" id="tokenValue">
            <?= esc($tokenEnabled ? ($token !== '' ? $token : '–') : 'TANPA TOKEN') ?>
        </div>
        <p class="text-muted mt-3 mb-0" id="tokenInfo">
            <?php if (! $tokenEnabled): ?>
                Quiz bisa dimulai tanpa token.
            <?php elseif ($nextRelease > 0): ?>
                Token berganti otomatis setiap <?= (int) $tokenMinutes ?> menit. Berikutnya pukul
                <strong id="tokenNext"><?= date('H:i', $nextRelease) ?></strong> (<span id="tokenLeft">–</span> lagi).
            <?php else: ?>
                Token belum dibuat. Hubungi administrator.
            <?php endif ?>
        </p>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    'use strict';
    // Token berganti di server (cron); perbarui tampilan tanpa muat ulang halaman.
    var box = document.getElementById('tokenValue');
    var offset = 0, next = <?= (int) $nextRelease ?>;
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var tick = function () {
        var el = document.getElementById('tokenLeft');
        if (!el || !next) { return; }
        var left = next - (Math.floor(Date.now() / 1000) + offset);
        el.textContent = left > 0 ? Math.floor(left / 60) + ':' + pad(left % 60) : 'sebentar';
    };
    var poll = function () {
        fetch('<?= site_url('status_tes/token') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 401) { window.location.reload(); return null; }
                return r.ok ? r.json() : null;
            })
            .then(function (d) {
                if (!d || !d.enabled) { return; }
                offset = d.now - Math.floor(Date.now() / 1000);
                if (d.token && box.textContent.trim() !== d.token) {
                    box.textContent = d.token;
                    box.classList.add('token-new');
                    setTimeout(function () { box.classList.remove('token-new'); }, 1500);
                }
                if (d.next) {
                    next = d.next;
                    var at = document.getElementById('tokenNext');
                    if (at) { var t = new Date(d.next * 1000); at.textContent = pad(t.getHours()) + ':' + pad(t.getMinutes()); }
                }
            })
            .catch(function () { /* coba lagi di putaran berikutnya */ });
    };
    tick();
    setInterval(tick, 1000);
    setInterval(poll, 15000);
})();
</script>
<?= $this->endSection() ?>
