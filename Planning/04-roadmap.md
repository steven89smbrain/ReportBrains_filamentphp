# 04 — Roadmap

Prinsip: **setiap milestone menghasilkan sesuatu yang bisa dijalankan dan dilihat.** Tidak ada
milestone yang isinya hanya "menyiapkan struktur".

Estimasi memakai satuan relatif (S/M/L), bukan tanggal, karena kecepatan pengerjaan belum diketahui.

> **M0–M5 sudah selesai** — rinciannya di
> [`Documentation/07-changelog.md`](../Documentation/07-changelog.md).

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

## M8 — Desainer kanvas ala Canva · L — **usulan, menunggu K10**

Jenis dokumen kedua dengan kanvas bebas (posisi, ukuran, rotasi, lapisan), keluaran PDF.

- [ ] Jawab K10 (kegunaan, terikat data atau tidak, satu/banyak halaman, cakupan fitur)
- [ ] Skema dokumen kanvas + validator (terpisah dari dokumen report)
- [ ] Editor kanvas di browser (Fabric.js atau Konva, keduanya MIT)
- [ ] Renderer kanvas → HTML berposisi absolut → PDF lewat driver yang sama (K3)
- [ ] Binding data per halaman (jika K10 memilih "terikat data")

**Selesai bila:** desain yang dibuat di kanvas tercetak ke PDF identik dengan yang tampil di editor.

---

## Posisi sekarang

Report sudah bisa dirancang visual, difilter dengan parameter, dan diekspor ke **PDF**, HTML, atau
Markdown dari panel. Secara fitur, produk sudah lengkap untuk rilis pertama.

Yang masih memisahkan dari **rilis berbayar pertama**:

1. **M6 — jalur distribusi** (private Packagist/Anystack) dan API runtime yang rapi. Tanpa ini
   produk tidak bisa dijual.
2. **K8 — akses panel** di aplikasi demo sebelum dipakai untuk demo publik.

Editor kanvas ala Canva (M8) sebaiknya **setelah** rilis pertama, dan setelah K10 dijawab.
