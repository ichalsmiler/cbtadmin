# DESIGN.md: CBT Admin

Arah tampilan panel, disalin dari jawaban pengguna (admin IT SMAN 2 Makassar).

- 15 November 2026: warna logo sekolah, terang bawaan + tombol gelap.
- 16 November 2026 (berlaku sekarang): pengguna memilih saran skill ui-ux-pro-max ("Real-Time / Operations",
  slate gelap + hijau status, Fira Sans/Fira Code), dengan batasan antislop yang disetujui pengguna:
  glassmorphism hanya aksen (R-10), Fira Code hanya untuk angka/token/kode (R-06).

## Identitas
- Panel operasional ujian sekolah; dipakai admin dan pengawas ruang selama ujian berlangsung (sering dibuka lama
  di ruang lab); tema gelap tersedia lewat tombol untuk mengurangi silau. Logo sekolah tetap tampil di bilah atas.

## Karakter
- Seimbang dan modern, rapat seperti dasbor (density 8).
- Tema bawaan TERANG (16 Nov 2026, permintaan pengguna); gelap lewat tombol.
- Dial: ENERGY 2 / RHYTHM 2 / MOTION 1 (hanya transisi hover/focus; tanpa scroll-reveal agar data tidak berkedip
  saat refresh otomatis).

## Palet
- Gelap (lewat tombol): latar `#0F172A`, kartu `#1B2336`, permukaan kedua `#272F42`, teks `#F8FAFC`, teks redup `#94A3B8`,
  bilah atas `#1E293B`.
- Aksen tunggal hijau `#22C55E` (tombol utama berteks gelap `#0F172A`, menu aktif, tautan `#4ADE80` di mode gelap,
  `#15803D` di mode terang).
- Status: sudah = hijau, sedang berjalan = biru langit, perlu perhatian = kuning, belum = abu.
- Terang (bawaan): latar `#F1F5F9`, kartu putih, teks `#0F172A`, teks redup `#475569`.

## Tipografi
- Fira Sans (teks, 400/500/600/700) dan Fira Code (token, angka statistik, kode), berkas woff2 disimpan lokal di
  `public/assets/fonts` (lisensi SIL OFL 1.1) karena server LAN tanpa internet.

## Efek
- Kaca (backdrop blur) hanya di bilah atas dan kartu token. Bayangan hanya untuk kartu statistik.
