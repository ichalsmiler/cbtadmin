# CBT Admin

Panel administrasi ujian CBT untuk Moodle, diakses di `http://<ip-server>/cbtadmin/`.
Dibangun dengan CodeIgniter 4 dan berjalan di server yang sama dengan Moodle.

## Status

| Menu | Status |
|---|---|
| Login, layout, Dashboard | Selesai |
| Daftar Ruang (ruang dan sesi) | Selesai |
| Daftar Peserta (Data CBTAdmin, Data Moodle, import/export Excel, buat user Moodle, reset password) | Selesai |
| Tools (pengaturan, logo, akun admin/proktor, backup database panel, bersihkan sesi, log aktivitas) | Selesai |
| Status Test (tes = kategori kursus Moodle, buka/tutup mapel, token bersama dengan release otomatis, Reset Default, buka/tutup/atur waktu quiz), Status Peserta (paksa submit, tambah waktu, hapus attempt) | Selesai |
| Reset Login Peserta (per peserta, massal, per ruang), No Attempt Check (export Excel) | Selesai |
| Hasil Ujian (tabel, statistik, Excel, PDF), Dokumen (kartu peserta 8 per A4, daftar hadir, laporan kehadiran, berita acara PDF, Excel) | Selesai |

## Isi folder ini

Folder ini hanya berisi kode panel (lapisan di atas CodeIgniter 4). Kerangka CodeIgniter
dan `vendor/` diunduh di server oleh `deploy/install.sh`.

- `app/` — controller, library, view, migrasi, rute.
- `public/assets/` — CSS dan JS panel. Bootstrap, Bootstrap Icons, dan jQuery diunduh ke
  `public/assets/vendor/` di server supaya panel jalan tanpa internet.
- `deploy/install.sh` — pemasang di server (dijalankan sebagai root).
- `deploy/deploy.sh` — kirim kode dari komputer ini lalu jalankan pemasang.

## Pemasangan

Syarat: server sudah berisi Moodle hasil `install-moodle.sh` (Nginx, PHP-FPM, MariaDB),
dan komputer ini bisa `ssh moodle` dengan sudo tanpa kata sandi.

```bash
bash deploy/deploy.sh
```

Pemasang mengerjakan:

1. Memasang Composer dan mengunduh CodeIgniter 4 ke `/var/www/cbtadmin`.
2. Menyalin kode panel dan mengunduh aset.
3. Membuat database `cbtadmin` dan user database `cbtadmin` (akses penuh ke database
   panel; baca/tulis ke database Moodle), lalu menulis `.env`.
4. Menjalankan migrasi (`admins`, `peserta`, `ruang`, `sesi`, `settings`, `activity_log`).
5. Membuat akun `admin` dengan kata sandi acak, disimpan di server di
   `/root/cbtadmin-credentials.txt`.
6. Menambahkan `/etc/nginx/snippets/cbtadmin.conf` dan memuatnya dari konfigurasi situs Moodle.

Menjalankan ulang `deploy.sh` hanya memperbarui kode; `.env`, database, dan akun admin
yang sudah ada tidak diubah.

## Konfigurasi `.env` (di server, `/var/www/cbtadmin/.env`)

| Kunci | Isi |
|---|---|
| `app.baseURL` | Alamat panel, diakhiri `/cbtadmin/` |
| `database.default.*` | Koneksi database panel |
| `moodle.hostname`, `moodle.database`, `moodle.username`, `moodle.password` | Koneksi database Moodle |
| `moodle.prefix` | Prefix tabel Moodle (bawaan `mdl_`) |
| `cbt.namaSekolah`, `cbt.npsn`, `cbt.webminUrl` | Nilai bawaan; bisa ditimpa dari menu Tools |
| `cbt.sessionTimeout` | Batas diam sebelum admin harus login ulang (detik) |

## Akun admin

Akun `admin` awal dibuat oleh pemasang. Akun lain (administrator atau proktor) dikelola dari
**Tools → Akun panel**. Proktor bisa memakai semua menu kecuali Tools. Akun yang dinonaktifkan,
dihapus, atau diganti rolenya langsung berlaku, termasuk untuk sesi yang sedang login.

Bila semua akun administrator terkunci, buat hash password di server:

```bash
php -r 'echo password_hash("KATA-SANDI", PASSWORD_DEFAULT), "\n";'
```

lalu masukkan hasilnya ke tabel `admins` (kolom `password_hash`, `role` = `admin`, `aktif` = 1).

## Backup

**Tools → Backup database panel** mengunduh berkas `.sql` berisi semua tabel panel (peserta beserta
password, ruang, sesi, pengaturan, akun, log). Data Moodle tidak termasuk. Untuk memulihkan:

```bash
mariadb cbtadmin < cbtadmin_backup_YYYYMMDD_HHMMSS.sql
```

## Status Test dan token

- **Daftar Tes** berisi kategori kursus Moodle. Kategori yang disimpan menjadi tes aktif; mata pelajaran
  adalah kursus di kategori itu (termasuk subkategorinya).
- Di bawah Daftar Tes tampil **Mata Pelajaran** (kursus di kategori yang dipilih) dengan sakelar
  aktif/nonaktif; **Simpan** menyimpan tes aktif sekaligus membuka atau menyembunyikan kursus
  (minimal 1 tetap aktif). **Reset Default** membuka
  kembali semua kursus di tes aktif, mis. untuk ujian susulan.
- **Token** (6 huruf) dipasang sebagai password di semua quiz pada tes aktif. **Release** membuat token
  baru seketika; cron `/etc/cron.d/cbtadmin` (`php spark cbt:token`, tiap menit) me-release token sesuai
  timer di **Setting → Setting Token** dan memasang token di quiz yang baru dibuat. Mematikan token
  mengosongkan password semua quiz di tes aktif.

## Plugin Moodle (`moodle/local/`)

`local_cbtidentitas` menampilkan nama dan kelas siswa (field *department*, atau nama belakang bila
kosong) di bawah judul halaman Daftar Ujian. Pasang di **kedua** server dengan menyalin foldernya ke
`/var/www/moodle/public/local/`, lalu di server utama jalankan
`sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive`.

## Server utama + cadangan (`deploy/ha/`)

Dua VPS, masing-masing dengan alamatnya sendiri di Cloudflare (mis. `utama.sekolah.sch.id` dan
`cadangan.sekolah.sch.id`). Siswa mengerjakan di alamat utama; server cadangan terus menyalin database
(replikasi MariaDB, jeda biasanya di bawah 1 detik) dan file Moodle (tiap menit) lewat jalur WireGuard
pribadi. Selama belum dipakai, alamat cadangan menampilkan halaman "Server cadangan, belum aktif".
Bila server utama rusak, satu perintah membuka ujian di alamat cadangan.

Kedua server juga boleh di jaringan lokal sekolah: isi `DOMAIN_UTAMA` / `DOMAIN_CADANGAN` dengan IP
server (mis. `172.16.32.154` dan `172.16.32.155`); skrip lalu memakai `http://` biasa dan langkah
Cloudflare di bawah dilewati.

Persiapan di **kedua** VPS (Debian 12/13):

1. Pasang Moodle dengan alamat server itu sendiri, mis. di cadangan
   `WWWROOT=https://cadangan.sekolah.sch.id bash deploy/install-moodle.sh`, lalu CBT Admin
   (`deploy/deploy.sh`). Versi Moodle kedua server harus sama.
2. Cloudflare: record A `utama` → IP server utama dan `cadangan` → IP server cadangan, keduanya
   **Proxied**; SSL/TLS mode **Full (strict)**; buat satu Origin Certificate untuk kedua alamat dan simpan
   di kedua server sebagai `/etc/ssl/cbt/origin.pem` dan `/etc/ssl/cbt/origin.key`.
3. Buka port **UDP 51820** di firewall penyedia VPS (untuk WireGuard).
4. Salin `deploy/ha/` ke kedua server, salin `ha.conf.contoh` menjadi `ha.conf`, isi, dan pastikan
   isinya sama persis di kedua server (`HA_SECRET` dibuat sekali dengan `openssl rand -hex 32`).

Pemasangan (sebagai root, berurutan):

```bash
bash ha-utama.sh pasang       # di server utama
bash ha-cadangan.sh pasang    # di server cadangan (data cadangan diganti salinan utama)
```

Sehari-hari: `bash ha-cadangan.sh status` (jeda replikasi, jumlah attempt dan jawaban di kedua server,
salinan file terakhir, backup terbaru).
Backup ada di server cadangan, `/var/backups/cbt`: database tiap 15 menit pada jam ujian, backup penuh
(database + moodledata) tiap 01:30, disimpan 7 hari.

Bila server utama rusak, di server cadangan:

```bash
bash ha-cadangan.sh pindah
```

Database dibuka untuk menulis, cron Moodle dan token dinyalakan, dan alamat cadangan mulai melayani
ujian. Umumkan alamat cadangan ke siswa; mereka login lagi dan melanjutkan quiz. Matikan server lama
(atau hapus DNS alamat utama) supaya tidak ada siswa yang masih mengerjakan di sana. Untuk menjadikan
server lama cadangan baru, di `ha.conf` kedua server tukar `UTAMA_IP` ↔ `CADANGAN_IP` dan
`DOMAIN_UTAMA` ↔ `DOMAIN_CADANGAN`, lalu jalankan `ha-utama.sh pasang` di server yang sekarang utama dan
`ha-cadangan.sh pasang` di server lama.

## Catatan teknis

- "User Aktif" dihitung dari `mdl_user.lastaccess`. Sesi Moodle disimpan di Redis (bukan `mdl_sessions`),
  jadi Reset Login membaca dan menghapus sesi lewat `resessionmanager` di jembatan.
- Aksi yang mengubah data ujian Moodle (buat user, reset password, waktu quiz, paksa submit,
  tambah waktu, hapus attempt) dijalankan lewat fungsi Moodle sendiri (`app/Bridge/moodle_bridge.php`), bukan SQL langsung.
- Password peserta harus memenuhi kebijakan password Moodle (bawaan: 8 karakter, huruf besar, huruf kecil, angka, simbol). Password acak panel berbentuk `Kmtr-482`. Untuk password yang lebih sederhana, longgarkan kebijakan di Moodle: Site administration → Security → Site security settings.
- Panel menyimpan password peserta apa adanya di tabel `peserta` supaya bisa dicetak di kartu.
