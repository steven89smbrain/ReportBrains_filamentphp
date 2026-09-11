# 04 — Roadmap

Prinsip: **setiap milestone menghasilkan sesuatu yang bisa dijalankan dan dilihat.** Tidak ada
milestone yang isinya hanya "menyiapkan struktur".

Estimasi memakai satuan relatif (S/M/L), bukan tanggal, karena kecepatan pengerjaan belum diketahui.

---

## M0 — Fondasi · S — **SELESAI** (11 Sep 2026)

- [x] `git init` + commit awal, remote ke `ReportBrains_filamentphp`
- [x] Filament v5.7.8 terpasang, panel admin di `/admin`
- [x] `User` mengimplementasikan `FilamentUser` (lihat K8 — belum aman untuk produksi)
- [x] Package `reportbrains/filament-report-designer` + path repository (symlink)
- [x] Plugin terdaftar di panel, halaman `admin/reports` muncul dari namespace package
- [x] 4 test membuktikan wiring: plugin terdaftar, page ditemukan, halaman render, akses tanpa login ditolak
- [ ] Buat user admin — **perlu dijalankan sendiri** (pilih password Anda):
      `php artisan make:filament-user`

**Selesai bila:** panel Filament terbuka dan menu "Reports" tampil dari kode package, bukan dari `app/`. ✅

---

## M1 — Template tersimpan · M

- [ ] Skema JSON v1 + kelas validator
- [ ] Migrasi `report_templates` (key, title, schema JSON, versi, timestamps)
- [ ] Filament Resource untuk CRUD template (form biasa dulu, belum editor visual)
- [ ] Loader yang bisa membaca template dari DB **dan** dari `resources/reports/*.json`
- [ ] Test: template invalid ditolak dengan pesan yang jelas

**Selesai bila:** template bisa dibuat, disimpan, dan dibaca kembali lewat kode.

---

## M2 — Sumber data · M

- [ ] `ReportData::source()` registry + kelas `DataSource`
- [ ] Introspeksi metadata: daftar kolom, tipe, relasi yang diizinkan
- [ ] Perakit query dari `data` di JSON, dengan `scope()` yang wajib diterapkan
- [ ] Deklarasi & validasi parameter
- [ ] Test keamanan: sumber tak terdaftar ditolak, field di luar whitelist ditolak, scope tak tertembus

**Selesai bila:** satu sumber data contoh (`penjualan`) bisa mengembalikan baris terfilter dari kode.

---

## M3 — Renderer Markdown + HTML · M

Sengaja **didahulukan sebelum editor**: renderer bisa dites tanpa UI, dan begitu jadi, editor punya
sesuatu untuk ditampilkan sebagai preview.

- [ ] Compiler: band diulang per baris, grouping, agregat (`sum`, `avg`, `count`)
- [ ] Evaluator ekspresi tersandbox + fungsi format lokal Indonesia (rupiah, tanggal, ribuan)
- [ ] Renderer Markdown
- [ ] Renderer HTML
- [ ] Test snapshot untuk keduanya

**Selesai bila:** `Report::make('x')->toMarkdown()` menghasilkan file MD benar dari data sungguhan.
Di titik ini poin 2 rencana awal sudah terpenuhi, tanpa editor visual sekali pun.

---

## M4 — Editor v1 (Builder Filament) · M

- [ ] Halaman editor memakai komponen Builder dari `filament/schemas` — drag-drop reorder sudah bawaan
- [ ] Blok: heading, teks, tabel, pemisah, spasi
- [ ] Panel field: menampilkan kolom dari sumber data terpilih, klik untuk menyisipkan binding
- [ ] Filter memakai `filament/query-builder`
- [ ] Preview langsung berdampingan dengan editor

**Selesai bila:** report sederhana bisa dirancang penuh lewat UI tanpa menyentuh JSON manual.
**Di sini produk sudah layak didemokan.**

---

## M5 — PDF · M

- [ ] Pilih paket PDF (uji `--dry-run` di PHP 8.5 dulu — lihat [05](05-keputusan-terbuka.md))
- [ ] Page setup: ukuran, orientasi, margin
- [ ] Header/footer halaman + nomor halaman
- [ ] Renderer PDF + test

**Selesai bila:** report yang sama menghasilkan PDF rapi dan MD, dari satu template.

---

## M6 — Runtime & distribusi · S

- [ ] Facade `Report` dengan API lengkap
- [ ] Artisan `report:render`
- [ ] Job antrian untuk report besar
- [ ] Ekspor XLSX/CSV (`openspout` sudah ikut terpasang bersama Filament)
- [ ] README package + dokumentasi pemakaian
- [ ] Tag rilis `v0.1.0`

**Selesai bila:** plugin bisa dipasang di aplikasi Laravel+Filament lain dan langsung berfungsi.

---

## M7 — Lanjutan · L

Dikerjakan berdasarkan umpan balik pemakaian nyata, bukan tebakan:

- [ ] Editor v2: kanvas custom (Alpine + SortableJS), blok bersarang, layout kolom
- [ ] Blok grafik
- [ ] Jadwal & pengiriman otomatis (email/Storage)
- [ ] Versioning template + draft/published
- [ ] Impor Markdown → blok
- [ ] Mode kanvas absolut untuk faktur/label (PDF saja)
- [ ] Bantuan AI menyusun template

---

## Urutan yang sengaja dipilih

Yang mungkin terasa berlawanan dengan intuisi: **renderer (M3) didahulukan sebelum editor (M4)**,
padahal editor yang paling terlihat.

Alasannya, editor tanpa renderer tidak bisa dibuktikan benar — yang terlihat hanya kotak-kotak yang
bisa digeser. Sebaliknya, renderer tanpa editor sudah memberi nilai penuh: template bisa ditulis
sebagai JSON dan report sudah bisa dihasilkan. Kalau anggaran waktu habis di tengah jalan, berhenti
setelah M3 masih meninggalkan alat yang berguna; berhenti setelah M4-tanpa-M3 meninggalkan UI yang
tidak menghasilkan apa-apa.
