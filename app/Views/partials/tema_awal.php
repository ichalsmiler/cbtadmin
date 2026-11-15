<?php // Pasang tema (terang/gelap) sebelum halaman digambar agar tidak berkedip; pilihan disimpan per browser. ?>
<script>
(function () {
    'use strict';
    var tema = 'light';
    try { tema = localStorage.getItem('cbt-tema') === 'dark' ? 'dark' : 'light'; } catch (e) { /* penyimpanan diblokir: tetap terang */ }
    document.documentElement.setAttribute('data-bs-theme', tema);
})();
</script>
