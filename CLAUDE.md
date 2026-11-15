# CBT Admin — peta proyek (untuk Claude; baca ini dulu, jangan menjelajah ulang)

Panel admin ujian CBT (CodeIgniter 4.7, PHP 8.4) di atas **Moodle 5.1.8+** (kode web di `public/`).
Meniru AIOCBT CBTAdmin (lms.sman2makassar.sch.id/cbtadmin). Semua teks UI berbahasa Indonesia.

## Server dan alur kerja
- Utama `moodle` = 172.16.32.154, cadangan `cadangan` = 172.16.32.155 (alias SSH, user smiler, sudo tanpa sandi;
  keduanya container LXC Proxmox, Debian 13, MariaDB 11.8, Redis untuk sesi, WITA).
- Panel di `/var/www/cbtadmin` (URL `/cbtadmin/`), Moodle di `/var/www/moodle`, data `/var/moodledata`.
- PHP tidak ada di PC ini: lint di server (`ssh moodle 'php -l /var/www/cbtadmin/<file>'`).
- Deploy panel: `bash deploy/deploy.sh moodle` (Git Bash). Perubahan kecil cukup ke `moodle`; `cadangan` disamakan
  sebelum ujian. Setelan + plugin Moodle: `bash deploy/setelan-moodle.sh <host>`.
- Commit: `git -c user.name=ichalsmiler -c user.email=ichalsmiler@users.noreply.github.com commit`; push hanya
  setelah ditanya ke pengguna. Naikkan `PanelController::VERSION` bila CSS/JS berubah (cache-buster).
- Uji Moodle lewat skrip CLI di server (`sudo -u www-data php /tmp/x.php`); jangan login web dengan akun uji.
  Data uji dibuat lalu dihapus. Login panel di browser dilakukan pengguna.

## Struktur kode panel (`app/`)
- `Controllers/PanelController.php` — basis: `render()`, `myRuang()` (ruang pengawas), `ruangList()`, `VERSION`.
- `Filters/Guard.php` — login, CSRF, timeout, role. `PROKTOR_PATHS` = menu yang boleh untuk pengawas (role `proktor`).
- `Views/layout/main.php` — array `$menu` sidebar; admin menyembunyikan `token`,`kehadiran`; Reset Login muncul
  hanya bila setting `lock_login`=1. Tombol "Kembali ke Admin" saat Tukar Akses (`session tukar_dari`).
- `Config/Routes.php` — semua rute (grup terfilter Guard).
- JS `public/assets/js/panel.js`: `data-post`+`data-fields`+`data-confirm-text` (POST via `#postForm`),
  `data-confirm-button` (form massal), `data-fill-modal`+`data-fill` (isi modal), `table.datatable` (DataTables,
  `data-order-col`), `a[data-pdf]`/`form[data-pdf]` (pratinjau PDF di `#pdfModal`, Unduh = `?unduh=1` → attachment
  di `Pdf::respond`). CSS `public/assets/css/panel.css`.
- Server utama RAM 1 GB: dompdf maks ±400 kartu per PDF (`Dokumen::MAKS_KARTU`, lebih → daftar tautan per
  ruang/kelas `dokumen/kartu_bagian`); gambar di PDF besar pakai path berkas (chroot `writable/uploads`), bukan data-URI.
- Partial `Views/partials/filter_tingkat.php` (select Tingkatan X/XI/XII lalu "Semua", `Libraries/Tingkat.php`);
  `Tingkat::dariRequest()` bawaan X, pilihan diingat di sesi `filter_tingkat` (dipakai juga ekspor tanpa ?tingkat=).

Menu → controller:
| Menu | Controller | Catatan |
|---|---|---|
| Dashboard | Dashboard | stat via `dashboard/stats` (JSON) |
| Daftar Peserta | Peserta | pengawas: `peserta_ruang` (`Peserta::ruang`, baca saja, ruangnya; tautan kotak siswa di dashboard); impor AIOCBT (`Excel::read`, header baris 9), No. Peserta = username bila kosong; Reset Peserta (ketik HAPUS) / centang "Hapus data lama" saat impor → `hapusSemua()` (buka suspend Moodle siswa terblokir, kosongkan peserta+absensi+blokir; centang `ruang=1` di Reset ikut hapus ruang, sesi, akun pengawas; akun Moodle tetap) |
| Status Test / Token | StatusTes | kategori Moodle = tes; simpan → hanya mapel dicentang + kategori itu tampil, lainnya disembunyikan; token bersama (`ActiveTest`, cron `cbt:token`) |
| Status Peserta | StatusPeserta | paksa submit, tambah waktu; tombol "Hapus attempt…" (admin) → HapusAttempt; "Login ganda" `status_peserta/login_ganda` (log user_loggedin per IP per tanggal) |
| Reset Login | ResetLogin | hanya dipakai saat Lock login aktif |
| Laporan Kehadiran | Kehadiran | absensi pengawas (filter tingkatan, simpan hanya yang tampil); tidak hadir → suspend Moodle hari itu (`Absensi`); token pengawas tampil di Dashboard (`partials/token_card`, menu Token dihapus, `/token` → dashboard) dan terkunci sampai ada absensi ruangnya hari ini (`ActiveTest::terkunciUntuk`, `mapelAktif`) |
| Blokir Siswa | BlokirSiswa | `Libraries/Blokir.php`, tabel `blokir`; tidak dibuka oleh absensi |
| Daftar Ruang | Ruang | kode login pengawas `NPSN-<huruf><no><2 acak>` (`Pengawas::usernameFor`), password 4 huruf kapital (`Pengawas::randomPassword`), Tukar Akses, Generate Kode, Cetak ID Login (`pdf/id_login`), nama lab (`ruang.lokasi`) |
| Dokumen | Dokumen | kartu login peserta per ruang/kelas (`pdf/kartu`: logo sekolah kiri + logo kanan `logo2` dari Info Sekolah, jam ujian per tingkatan `jam_<X|XI|XII>_mulai/selesai`, `catatan_kartu`), daftar hadir; Laporan Kehadiran admin `dokumen/laporan_kehadiran` 2 tab: `?tab=pengawas` (rekap absensi pengawas, `Absensi::rekap($tgl,$tingkat)`, view `dokumen/absensi`; alamat lama `dokumen/absensi` dialihkan) dan `?tab=ujian` (hadir = punya attempt, berita acara) |
| Hasil Ujian / No Attempt | HasilUjian / NoAttempt | tab `?tab=cepat` "Selesai terlalu cepat" (pw/pm/pn: % batas waktu, % median, % nilai) |
| Tools | Tools | accordion: Moodle Tools (Kelola Test `tools/test_manage`, Cron, Purge/Delete cache, Anti Submit) + CBTAdmin Tools (Info Sekolah, Info Kegiatan, Lock/Resume/Anti Newtab, Ganti Password) + Lainnya (akun, panel, backup, log) |

Library penting: `Moodle` (koneksi DB Moodle langsung, baca; `quizzes()`, `categories()`, `coursesInCategory()`,
`allCourses()`), `MoodleBridge::call(action, payload)` (tulis lewat API Moodle, CLI) dan `MoodleBridge::cron()`,
`QuizStatus` (status peserta per quiz, `filter(rows, ruang, kelas, tingkat)`, `latestAttemptIds`),
`Settings` (tabel `settings` skey/svalue), `School::info()` (identitas + logo/ttd data-URI untuk PDF),
`Pengawas` (akun pengawas per ruang), `ActivityLog::add()`, `Pdf::respond()` (dompdf), `Excel`.

Jembatan `Bridge/moodle_bridge.php` (aksi): create_users, reset_passwords, quiz_times, finish_attempts,
delete_attempts, add_time, list_sessions, kill_sessions, quiz_password, course_visibility, category_visibility,
suspend_users, cbtujian_config, purge_caches, create_category, create_course, session_gc.

Migrasi `Database/Migrations/`: tabel admins (role admin/proktor, ruang_id, password_plain), peserta, ruang
(+lokasi), sesi, settings, activity_log, absensi, blokir.

## Moodle (kustomisasi)
- Semua setelan ada di `deploy/moodle/setelan.php` (idempoten), CSS di `deploy/moodle/boost.scss` (tema Boost,
  antara penanda `CBT MULAI/SELESAI`), teks bahasa di `deploy/moodle/lang/` (→ `moodledata/lang/{id,en}_local`).
  Ubah di sana lalu jalankan `setelan-moodle.sh`; jangan ubah manual lewat Site administration.
- Plugin `moodle/local/`: `cbtblokir` (pesan "User Anda terblokir"), `cbtnama` (nama depan di samping ikon profil),
  `cbtujian` (Anti Submit / Lock login / Resume timer / Anti Newtab; setelan plugin `local_cbtujian` dikirim dari
  Tools via aksi `cbtujian_config`, Lock login mematikan `limitconcurrentlogins`).
- Tampilan siswa: menu atas hanya "Daftar Ujian" (+ nama), sapaan "nama - kelas" (first name = nama, last name =
  kelas), tanpa Dasbor/Beranda/pesan/lonceng/tombol ?/filter; quiz 1 percobaan dikunci, tinjauan tanpa kunci jawaban.

## Lain-lain
- `deploy/install-moodle.sh` (server baru, `ZONA_WAKTU`), `deploy/install.sh` (panel; dilewati migrasi di cadangan HA),
  `deploy/ha/` (replikasi MariaDB + rsync, mode LAN tanpa WireGuard; `/etc/cbt-ha-peran`).
- README.md berisi panduan lengkap untuk manusia.
