# 05 — Keputusan

## Sudah diputuskan

Dicatat supaya alasannya tidak hilang dan tidak dibahas ulang.

| # | Keputusan | Konsekuensi |
|---|---|---|
| K1 | **Produk dijual, bayar sekali per proyek** | Lisensi package `proprietary` + `LICENSE.md`. Distribusi lewat private Packagist/Anystack. Tanpa validasi kunci lisensi di runtime |
| K2 | **Editor berlapis**: ramah pengguna bisnis sebagai tampilan utama, plus tampilan JSON untuk developer | Label kolom **wajib** saat mendaftarkan sumber data — sudah dipaksakan di `EloquentSource::addField()` |
| K4 | **Kepemilikan & tenancy opsional, bisa dikonfigurasi** | Kolom `owner_*` dan `tenant_*` selalu ada tapi nullable; bawaan mati. Mengaktifkan nanti = backfill data, bukan migrasi |
| K5 | **Semua bahasa Inggris** untuk UI dan `Documentation/` | `Planning/` tetap Indonesia sebagai catatan internal |
| K7 | **Eloquent dulu, di balik interface** | `DataSource` adalah interface yang bicara dalam baris, bukan query builder. Sumber view/stored procedure/API bisa ditambah tanpa merombak |
| — | **Dukung MySQL + PostgreSQL + SQLite** | Kolom `json` Laravel yang portabel; tidak memakai operator JSON spesifik vendor |

## Masih terbuka

Diurutkan berdasar seberapa mahal kalau salah pilih di belakang hari.

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

### K6 — Sudah dicek ada plugin serupa? · sebaiknya sebelum rilis

Telusuri katalog plugin Filament dan Packagist untuk report builder. Kalau sudah ada yang mendekati,
periksa harga dan kelengkapannya — itu menentukan posisi dan harga produk ini. Belum diperiksa;
perlu akses pencarian web.

---

### K8 — Siapa yang boleh mengakses panel admin? · blokir sebelum deploy

Filament menolak akses panel dengan 403 di environment non-`local` kecuali model `User`
mengimplementasikan `FilamentUser`. Kontraknya sudah dipasang di `app/Models/User.php`, tapi
`canAccessPanel()` mengembalikan `true` — **setiap user terdaftar bisa masuk panel admin.**

Ini hanya soal aplikasi demo ini, bukan package-nya; pembeli akan memakai aturan mereka sendiri
(sudah didokumentasikan di `Documentation/01-installation.md`). Tetap perlu dibereskan sebelum
aplikasi ini dipakai untuk demo publik.

---

### K9 — Bagaimana parameter report memfilter data? · sebaiknya sebelum rilis pertama

Ditemukan saat M4. Ada dua celah yang saling terkait:

1. **Parameter tidak memfilter apa pun.** Parameter yang didaftarkan developer lewat
   `addParameter()` divalidasi dan bisa ditampilkan lewat `{{ params.from }}`, tapi **tidak pernah
   dipakai untuk menyaring baris**. Filter di dokumen hanya menerima nilai tetap. Artinya report
   "penjualan periode X s/d Y" belum bisa dibuat.
2. **Parameter ada di dua tempat.** Dokumen punya blok `params`, sumber data juga punya
   `addParameter()`. Yang dipakai saat runtime hanya milik sumber data; `params` di dokumen
   tidak berpengaruh, dan editor visual belum bisa mengubahnya.

Pilihan yang masuk akal:

| Opsi | Cara kerja | Konsekuensi |
|---|---|---|
| **Filter merujuk parameter** | Nilai filter boleh `{{ params.from }}`; parameter tetap didefinisikan developer di sumber data | Paling aman (tetap lewat whitelist); `params` di dokumen dihapus dari skema |
| Parameter didefinisikan di dokumen | Perancang report membuat parameternya sendiri di editor | Paling fleksibel bagi pengguna bisnis; perlu aturan tipe & validasi baru |
| Scope menerima parameter | `scope(fn ($query, $params) => ...)` | Hanya developer yang bisa memakai parameter; perancang report tidak |

## Catatan teknis M4 (bukan keputusan)

**Filter tidak memakai `filament/query-builder`,** berbeda dari rencana awal. Operator query-builder
menerapkan kondisi langsung ke query Eloquent memakai nama kolom, sehingga akan melewati whitelist
sumber data dan mengikat filter ke Eloquent — bertentangan dengan keputusan K7 (sumber data bicara
dalam baris). Filter dibangun dengan Repeater yang hanya menawarkan field terdaftar dan operator
sesuai tipenya, lalu tetap divalidasi `ReportQueryFactory`.
