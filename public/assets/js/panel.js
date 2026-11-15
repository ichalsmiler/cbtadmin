/* CBT Admin: perilaku umum panel. */
(function () {
    'use strict';

    // ---------------------------------------------------------------- Perubahan belum disimpan
    // Isian formulir POST (pengaturan, nama ruang, absensi, ...) yang diubah lalu ditinggal tanpa
    // Simpan memunculkan peringatan browser. Tidak berlaku untuk filter (GET), centang baris tabel
    // massal (data-bulk), formulir di modal, atau elemen/formulir ber-data-no-dirty.
    var dirty = false;
    function watchDirty(event) {
        var el = event.target;
        var form = el && el.form;
        if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') { return; }
        if (form.matches('[data-bulk], [data-no-dirty]') || el.matches('[data-no-dirty]') || form.closest('.modal')) { return; }
        dirty = true;
    }
    document.addEventListener('input', watchDirty);
    document.addEventListener('change', watchDirty);
    // Dikirim (dan tidak dibatalkan handler lain, mis. konfirmasi): perubahan dianggap tersimpan.
    // Dipasang lewat setTimeout supaya berjalan setelah handler submit lain di berkas ini.
    setTimeout(function () {
        document.addEventListener('submit', function (event) { if (!event.defaultPrevented) { dirty = false; } });
    }, 0);
    window.addEventListener('beforeunload', function (event) {
        if (!dirty) { return; }
        event.preventDefault();
        event.returnValue = 'Perubahan belum disimpan.';
    });

    // ---------------------------------------------------------------- Konfirmasi
    var pendingAction = null;
    function confirmThen(text, action) {
        pendingAction = action;
        document.getElementById('confirmText').textContent = text;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
    }
    var okButton = document.getElementById('confirmOk');
    if (okButton) {
        okButton.addEventListener('click', function () {
            var action = pendingAction;
            pendingAction = null;
            dirty = false;  // aksi ini mengirim formulir / pindah halaman dengan sengaja
            if (action) { action(); }
        });
    }

    // Formulir dengan data-confirm="...": minta konfirmasi sebelum dikirim.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.dataset && form.dataset.confirm) {
            event.preventDefault();
            confirmThen(form.dataset.confirm, function () { form.submit(); });
            return;
        }
        if (form.matches && form.matches('[data-bulk]')) { addOffPageChecks(form); }
    });

    // Tombol data-post="url": kirim POST lewat #postForm setelah konfirmasi.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-post]');
        if (!button) { return; }
        confirmThen(button.dataset.confirmText || 'Lanjutkan?', function () {
            var form = document.getElementById('postForm');
            form.querySelectorAll('.post-extra').forEach(function (el) { el.remove(); });
            var fields = JSON.parse(button.dataset.fields || '{}');
            Object.keys(fields).forEach(function (name) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = fields[name];
                hidden.className = 'post-extra';
                form.appendChild(hidden);
            });
            form.action = button.dataset.post;
            form.submit();
        });
    });

    // Tombol submit dengan data-confirm-button di formulir massal.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-confirm-button]');
        if (!button) { return; }
        event.preventDefault();
        var form = button.form;
        confirmThen(button.dataset.confirmButton, function () {
            addOffPageChecks(form);
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = button.name;
            hidden.value = button.value;
            form.appendChild(hidden);
            form.submit();
        });
    });

    // ---------------------------------------------------------------- Modal isian
    // Tombol data-fill-modal="#id" data-fill='{"nama":"..."}' mengisi input bernama sama.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-fill-modal], [data-ruang]');
        if (!button) { return; }
        var modal = document.querySelector(button.dataset.fillModal || '#ruangModal');
        var data = JSON.parse(button.dataset.fill || button.dataset.ruang);
        Object.keys(data).forEach(function (name) {
            var field = modal.querySelector('[name="' + name + '"]');
            if (field) { field.value = data[name] === null ? '' : data[name]; }
        });
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    // ---------------------------------------------------------------- Pratinjau PDF
    // Tautan a[data-pdf] dan formulir form[data-pdf] (GET) dibuka di modal dulu; tombol Unduh
    // meminta berkas yang sama dengan ?unduh=1 (Content-Disposition: attachment).
    function showPdf(url) {
        var modal = document.getElementById('pdfModal');
        var frame = document.getElementById('pdfFrame');
        var loading = document.getElementById('pdfLoading');
        loading.classList.remove('d-none');
        var download = document.getElementById('pdfDownload');
        var setDownload = function (u) { download.href = u + (u.indexOf('?') < 0 ? '?' : '&') + 'unduh=1'; };
        frame.onload = function () {
            loading.classList.add('d-none');
            // Isi bingkai bisa berpindah (mis. daftar kartu per ruang → PDF ruang itu): Unduh ikut berkas yang tampil.
            try {
                var shown = frame.contentWindow.location.href;
                if (shown && shown !== 'about:blank') { setDownload(shown); }
            } catch (e) { /* lain asal: biarkan */ }
        };
        frame.src = url;
        setDownload(url);
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }
    var pdfModal = document.getElementById('pdfModal');
    if (pdfModal) {
        pdfModal.addEventListener('hidden.bs.modal', function () { document.getElementById('pdfFrame').src = 'about:blank'; });
        document.getElementById('pdfPrint').addEventListener('click', function () {
            var frame = document.getElementById('pdfFrame');
            try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { window.open(frame.src, '_blank'); }
        });
    }
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-pdf]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) { return; }
        event.preventDefault();
        showPdf(link.href);
    });
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches || !form.matches('form[data-pdf]')) { return; }
        event.preventDefault();
        var submitter = event.submitter;
        var action = (submitter && submitter.getAttribute('formaction')) || form.action;
        var params = new URLSearchParams(new FormData(form, submitter || undefined));
        var query = params.toString();
        showPdf(action + (query ? (action.indexOf('?') < 0 ? '?' : '&') + query : ''));
    });

    // ---------------------------------------------------------------- Tabel
    var tables = [];
    document.querySelectorAll('table.datatable').forEach(function (el) {
        if (typeof DataTable === 'undefined') { return; }
        tables.push(new DataTable(el, {
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100, 500],
            order: [[parseInt(el.dataset.orderCol || '0', 10), 'asc']],
            language: {
                search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ baris',
                info: '_START_–_END_ dari _TOTAL_', infoEmpty: 'Tidak ada data',
                infoFiltered: '(disaring dari _MAX_)', zeroRecords: 'Tidak ada yang cocok',
                emptyTable: 'Belum ada data',
                paginate: { first: '«', last: '»', next: '›', previous: '‹' }
            }
        }));
        // data-toolbar="#id": pindahkan "Tampilkan n baris" dan "Cari" ke baris filter itu (sejajar).
        var toolbar = el.dataset.toolbar && document.querySelector(el.dataset.toolbar);
        var wrap = el.closest('.dt-container');
        if (toolbar && wrap) {
            var length = wrap.querySelector('.dt-length');
            var search = wrap.querySelector('.dt-search');
            if (length) {
                // Jangan ikut terkirim saat formulir filter (GET) dikirim.
                length.querySelectorAll('select').forEach(function (s) { s.removeAttribute('name'); });
                toolbar.appendChild(length);
            }
            if (search) { search.classList.add('ms-auto'); toolbar.appendChild(search); }
            // Baris atas DataTables yang kini kosong disembunyikan.
            var top = wrap.querySelector('.dt-layout-row');
            if (top && !top.querySelector('.dt-length, .dt-search, .dt-info, .dt-paging, table')) { top.classList.add('d-none'); }
        }
    });

    function tableOf(form) {
        return tables.filter(function (t) { return form.contains(t.table().node()); })[0];
    }

    // Baris di halaman lain tidak ada di DOM: sertakan centangnya sebagai input tersembunyi.
    function addOffPageChecks(form) {
        form.querySelectorAll('.bulk-extra').forEach(function (el) { el.remove(); });
        var table = tableOf(form);
        if (!table) { return; }
        table.$('input[name="ids[]"]:checked').each(function () {
            if (document.body.contains(this)) { return; }
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'ids[]';
            hidden.value = this.value;
            hidden.className = 'bulk-extra';
            form.appendChild(hidden);
        });
    }

    // Centang semua baris yang lolos pencarian, di semua halaman.
    document.addEventListener('change', function (event) {
        var master = event.target.closest('[data-check-all]');
        if (!master) { return; }
        var table = tableOf(master.form);
        if (table) {
            table.$('input[name="ids[]"]', { search: 'applied' }).prop('checked', master.checked);
        }
    });

    // ---------------------------------------------------------------- Kelas mengikuti Tingkatan
    // Pilihan Kelas hanya berisi kelas dari Tingkatan terpilih; Tingkatan diganti → Kelas kembali ke
    // pilihan pertama ("Semua kelas"). Listener capture supaya jalan sebelum onchange="this.form.submit()".
    var tingkatOf = function (kelas) {
        var m = /^\s*(XII|XI|X)(?![A-Z])/i.exec(kelas || '');
        return m ? m[1].toUpperCase() : '';
    };
    var saringKelas = function (tingkat, kelas, reset) {
        Array.prototype.forEach.call(kelas.options, function (opt, i) {
            var tampil = i === 0 || tingkat.value === '' || tingkatOf(opt.value) === tingkat.value;
            opt.hidden = !tampil;
            opt.disabled = !tampil;
        });
        if (reset || kelas.selectedOptions[0] && kelas.selectedOptions[0].hidden) {
            kelas.selectedIndex = 0;
        }
    };
    var tingkatSel = document.getElementById('fTingkat');
    var kelasSel = document.getElementById('fKelas');
    if (tingkatSel && kelasSel && tingkatSel.form === kelasSel.form) {
        saringKelas(tingkatSel, kelasSel, false);
        document.addEventListener('change', function (e) {
            if (e.target === tingkatSel) { saringKelas(tingkatSel, kelasSel, true); }
        }, true);
    }

    // ---------------------------------------------------------------- Lain-lain
    var updateButton = document.getElementById('btnCekUpdate');
    if (updateButton) {
        updateButton.addEventListener('click', function () {
            document.getElementById('updateInfo').classList.remove('d-none');
        });
    }

    // Status Peserta: hitung mundur sisa waktu, dan muat ulang halaman berkala
    // selama admin tidak sedang memilih, mengetik, atau membuka jendela.
    window.CbtLive = {
        start: function (serverNow, seconds) {
            var offset = serverNow - Math.floor(Date.now() / 1000);
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            var tick = function () {
                var now = Math.floor(Date.now() / 1000) + offset;
                document.querySelectorAll('[data-deadline]').forEach(function (el) {
                    var left = parseInt(el.dataset.deadline, 10) - now;
                    if (left <= 0) {
                        el.textContent = 'habis';
                        el.className = 'text-danger fw-semibold';
                        return;
                    }
                    var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
                    el.textContent = (h > 0 ? h + ':' + pad(m) : m) + ':' + pad(s);
                    el.className = left <= 300 ? 'text-danger fw-semibold' : '';
                });
            };
            tick();
            setInterval(tick, 1000);

            var busy = function () {
                var table = tables[0];
                var checked = table ? table.$('input[name="ids[]"]:checked').length > 0
                    : document.querySelector('input[name="ids[]"]:checked');
                var active = document.activeElement;
                return checked || document.querySelector('.modal.show')
                    || (active && /^(INPUT|SELECT|TEXTAREA)$/.test(active.tagName));
            };
            setInterval(function () { if (!busy()) { window.location.reload(); } }, seconds * 1000);
        }
    };

    // Dashboard: perbarui angka dan bar secara berkala.
    window.CbtDashboard = {
        start: function (url, seconds) {
            var format = new Intl.NumberFormat('id-ID');
            var apply = function (data) {
                ['ruang', 'users', 'aktif', 'belum', 'sudah'].forEach(function (key) {
                    var el = document.querySelector('[data-stat="' + key + '"]');
                    if (!el) { return; }
                    var v = data[key];
                    el.textContent = v === null || v === undefined ? '–' : (typeof v === 'number' ? format.format(v) : v);
                });
                document.getElementById('moodleError').classList.toggle('d-none', !data.moodleError);
                if (!data.sys) { return; } // pengawas: tanpa informasi server
                ['cpu', 'mem', 'disk'].forEach(function (key) {
                    var bar = document.querySelector('[data-sys-bar="' + key + '"]');
                    var percent = data.sys[key].percent;
                    bar.style.width = percent + '%';
                    bar.textContent = percent + '%';
                    bar.classList.toggle('warn', percent >= 70 && percent < 90);
                    bar.classList.toggle('crit', percent >= 90);
                    if (key !== 'cpu') {
                        document.querySelector('[data-sys-text="' + key + '"]').textContent = data.sys[key].text;
                    }
                });
            };
            var tick = function () {
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (response) {
                        if (response.status === 401) { window.location.reload(); return null; }
                        return response.ok ? response.json() : null;
                    })
                    .then(function (data) { if (data) { apply(data); } })
                    .catch(function () { /* jaringan putus sementara: coba lagi di putaran berikutnya */ });
            };
            setInterval(tick, seconds * 1000);
        }
    };
})();
