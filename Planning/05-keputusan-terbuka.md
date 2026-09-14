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
| K3 | **PDF lewat spatie/laravel-pdf (MIT), driver Chromium sebagai rekomendasi** — `chrome` bawaan; `browsershot`, `gotenberg`, `dompdf` bisa dipilih lewat config | Semua open source tanpa langganan. Chromium dipilih karena editor berjalan di browser: PDF yang dicetak mesin browser sama persis dengan preview, dan mendukung CSS modern (posisi bebas, rotasi, flex/grid) yang dibutuhkan editor ala Canva. Dikecualikan: mPDF (GPL-2.0-only, berisiko untuk plugin berbayar) dan Cloudflare (layanan berbayar). Package hanya *menyarankan* driver, pembeli memilih sesuai servernya |
| K9 | **Filter merujuk parameter** (`{{ params.x }}`), parameter tetap didefinisikan developer di sumber data | `params` di dokumen dihapus. Filter berparameter kosong dilewati; tanggal vs kolom datetime mencakup sehari penuh. Editor punya pilihan "Compare with" dan input parameter untuk preview |
| — | **Dukung MySQL + PostgreSQL + SQLite** | Kolom `json` Laravel yang portabel; tidak memakai operator JSON spesifik vendor |

## Masih terbuka

Diurutkan berdasar seberapa mahal kalau salah pilih di belakang hari.

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

## Catatan teknis M4 (bukan keputusan)

**Filter tidak memakai `filament/query-builder`,** berbeda dari rencana awal. Operator query-builder
menerapkan kondisi langsung ke query Eloquent memakai nama kolom, sehingga akan melewati whitelist
sumber data dan mengikat filter ke Eloquent — bertentangan dengan keputusan K7 (sumber data bicara
dalam baris). Filter dibangun dengan Repeater yang hanya menawarkan field terdaftar dan operator
sesuai tipenya, lalu tetap divalidasi `ReportQueryFactory`.

---

### K10 — Editor kanvas ala Canva: untuk apa dan seberapa jauh? · blokir M8

Diminta saat M5. **Ini produk yang berbeda dari editor sekarang**, jadi perlu dibatasi dulu.

Editor saat ini berbasis **aliran blok per band** — itu yang membuat keluaran Markdown mungkin dan
yang membuat report berulang per baris data. Editor ala Canva berbasis **kanvas bebas**: elemen
punya posisi X/Y, ukuran, rotasi, lapisan. Konsekuensinya:

- **Hanya PDF (dan mungkin gambar).** Posisi bebas tidak punya padanan di Markdown.
- **Jenis dokumen terpisah**, bukan mode dari dokumen yang ada — skema, validasi, dan renderer sendiri.
- **Butuh editor JavaScript** di browser. Kandidat yang sudah dicek, keduanya MIT: Fabric.js 7.4.0 dan
  Konva 10.5.0. Keputusan PDF (K3) sudah menyiapkan jalannya: Chromium mencetak posisi absolut,
  rotasi, dan font web persis seperti yang tampil di kanvas.

Pertanyaan yang perlu dijawab sebelum membangun:

1. **Dipakai untuk apa?** Sertifikat, faktur/kuitansi, label & stiker, kartu nama, poster? Ini
   menentukan fitur minimum (misalnya label butuh ukuran kertas custom dan banyak label per halaman).
2. **Terikat data atau desain statis?** Misalnya satu sertifikat per peserta dari database — itu
   "mail merge" dan jauh lebih bernilai jual daripada kanvas kosong.
3. **Satu halaman atau banyak?** Kanvas multi-halaman jauh lebih mahal.
4. **Seberapa mirip Canva?** Teks & gambar & bentuk dasar dengan snapping, atau juga template galeri,
   grup, efek, dan undo tak terbatas?

Rekomendasi awal: mulai dari **kanvas satu halaman yang terikat data** (sertifikat/faktur/label),
karena itu yang paling membedakan produk dari plugin report biasa.
