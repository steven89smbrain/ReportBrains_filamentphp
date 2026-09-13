# 04 — Roadmap

Prinsip: **setiap milestone menghasilkan sesuatu yang bisa dijalankan dan dilihat.** Tidak ada
milestone yang isinya hanya "menyiapkan struktur".

Estimasi memakai satuan relatif (S/M/L), bukan tanggal, karena kecepatan pengerjaan belum diketahui.

> **M0–M4 sudah selesai** — rinciannya di
> [`Documentation/07-changelog.md`](../Documentation/07-changelog.md).

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

Sejak M4, **produk sudah layak didemokan**: report bisa dirancang lewat UI, dipreview dengan data
sungguhan, dan dirender jadi Markdown/HTML.

Yang masih memisahkan dari **rilis berbayar pertama** bukan fitur editor lagi, melainkan:

1. **Jalur distribusi** (bagian dari M6) — tanpa itu produk tidak bisa dijual.
2. **K9 — parameter belum memfilter data** (lihat `05-keputusan-terbuka.md`). Report tanpa filter
   rentang tanggal akan terasa setengah jadi bagi pembeli.
3. **M5 (PDF)** — hampir pasti diminta pembeli, tapi bisa menyusul setelah rilis pertama.
