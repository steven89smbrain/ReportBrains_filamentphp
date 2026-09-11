# 05 — Keputusan

## Sudah diputuskan

Dicatat supaya alasannya tidak hilang dan tidak dibahas ulang.

| # | Keputusan | Konsekuensi |
|---|---|---|
| K1 | **Produk dijual, bayar sekali per proyek** | Lisensi package `proprietary` + `LICENSE.md`. Distribusi lewat private Packagist/Anystack. Tanpa validasi kunci lisensi di runtime |
| K4 | **Kepemilikan & tenancy opsional, bisa dikonfigurasi** | Kolom `owner_*` dan `tenant_*` selalu ada tapi nullable; bawaan mati. Mengaktifkan nanti = backfill data, bukan migrasi |
| K5 | **Semua bahasa Inggris** untuk UI dan `Documentation/` | `Planning/` tetap Indonesia sebagai catatan internal |
| — | **Dukung MySQL + PostgreSQL + SQLite** | Kolom `json` Laravel yang portabel; tidak memakai operator JSON spesifik vendor |

## Masih terbuka

Diurutkan berdasar seberapa mahal kalau salah pilih di belakang hari.

---

### K2 — Siapa yang memakai editornya? · blokir M4

- **Developer** → boleh menampilkan nama field mentah, ekspresi, dan JSON. Editor lebih sederhana.
- **Pengguna bisnis (staf keuangan/operasional)** → wajib label ramah, tanpa istilah teknis,
  banyak validasi dan pengaman. Biaya UI naik signifikan.

Untuk produk yang dijual, jawabannya juga menentukan harga dan cara memasarkan: alat developer
dan alat pengguna akhir bukan pasar yang sama.

---

### K3 — Paket PDF mana? · blokir M5

| Pilihan | Kelebihan | Kekurangan |
|---|---|---|
| `spatie/laravel-pdf` (Chrome headless) | Dukungan CSS modern paling baik, hasil paling mirip HTML | Butuh Node + Chromium di server pembeli — hambatan pemasangan yang nyata untuk produk berbayar |
| `dompdf` / `barryvdh/laravel-dompdf` | PHP murni, tanpa dependensi luar; paling mudah dipasang pembeli | Dukungan CSS terbatas, layout kompleks sering meleset |
| `typst` | Kualitas cetak sangat baik, cepat | Perlu binary eksternal, harus membuat renderer sendiri |

**Wajib diuji** dengan `composer require … --dry-run` di PHP 8.5 sebelum diputuskan.

Pertimbangan tambahan karena dijual: dependensi yang sulit dipasang akan jadi keluhan dukungan
nomor satu. Mungkin layak mendukung dua-duanya dan membiarkan pembeli memilih.

---

### K6 — Sudah dicek ada plugin serupa? · sebaiknya sebelum M2

Telusuri katalog plugin Filament dan Packagist untuk report builder. Kalau sudah ada yang mendekati,
periksa harga dan kelengkapannya — itu menentukan posisi dan harga produk ini. Belum diperiksa;
perlu akses pencarian web.

---

### K7 — Sumber data selain Eloquent? · pengaruhi desain M2

Perlukah mendukung query builder mentah, view database, stored procedure, atau API eksternal?
Kalau kelak perlu, `DataSource` harus dirancang sebagai **interface** sejak awal, bukan kelas yang
terikat Eloquent — murah sekarang, mahal nanti.

Keputusan ini paling mendesak karena M2 adalah milestone berikutnya.

---

### K8 — Siapa yang boleh mengakses panel admin? · blokir sebelum deploy

Filament menolak akses panel dengan 403 di environment non-`local` kecuali model `User`
mengimplementasikan `FilamentUser`. Kontraknya sudah dipasang di `app/Models/User.php`, tapi
`canAccessPanel()` mengembalikan `true` — **setiap user terdaftar bisa masuk panel admin.**

Ini hanya soal aplikasi demo ini, bukan package-nya; pembeli akan memakai aturan mereka sendiri
(sudah didokumentasikan di `Documentation/01-installation.md`). Tetap perlu dibereskan sebelum
aplikasi ini dipakai untuk demo publik.
