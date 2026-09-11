# 04 — Roadmap

Prinsip: **setiap milestone menghasilkan sesuatu yang bisa dijalankan dan dilihat.** Tidak ada
milestone yang isinya hanya "menyiapkan struktur".

Estimasi memakai satuan relatif (S/M/L), bukan tanggal, karena kecepatan pengerjaan belum diketahui.

> **M0–M3 sudah selesai** — rinciannya di
> [`Documentation/07-changelog.md`](../Documentation/07-changelog.md).

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

- [ ] Pilih paket PDF (uji `--dry-run` di PHP 8.5 dulu — lihat K3 di [05](05-keputusan-terbuka.md))
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
- [ ] Perbarui `Documentation/` untuk fitur M2–M6
- [ ] Siapkan jalur distribusi berbayar (private Packagist / Anystack)
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

## Posisi sekarang

Renderer sengaja didahulukan sebelum editor, dan itu terbukti tepat: setelah M3, produk sudah
**berguna tanpa editor visual sama sekali** — template ditulis sebagai JSON dan report sudah
keluar sebagai Markdown/HTML.

Untuk produk yang dijual, **titik layak rilis paling awal adalah akhir M4**: pengguna bisa
merancang report lewat UI dan mendapat keluaran. M5 (PDF) hampir pasti diminta pembeli, tapi
tidak memblokir rilis pertama.
