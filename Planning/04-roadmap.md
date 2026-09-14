# 04 — Roadmap

Prinsip: **setiap milestone menghasilkan sesuatu yang bisa dijalankan dan dilihat.** Tidak ada
milestone yang isinya hanya "menyiapkan struktur".

Estimasi memakai satuan relatif (S/M/L), bukan tanggal, karena kecepatan pengerjaan belum diketahui.

> **M0–M6 sudah selesai** — rinciannya di
> [`Documentation/07-changelog.md`](../Documentation/07-changelog.md).

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

**Kode siap dirilis sebagai v1.0.0.** Report dirancang visual, difilter dengan parameter, dan
dijalankan dari panel, kode, antrian, maupun command line, ke PDF, Excel, CSV, HTML, atau Markdown.
Package sudah terbukti bisa dipasang di aplikasi Laravel + Filament yang baru.

Yang tersisa sebelum bisa dijual semuanya **langkah Anda**, bukan kode:

1. Jalankan checklist di `06-rilis-dan-distribusi.md` (repo package, produk Anystack, tag v1.0.0).
2. Putuskan **K11** — di mana dokumentasi untuk pembeli.
3. **K8** — akses panel di aplikasi demo sebelum dipakai untuk demo publik.

Setelah rilis: M7 (fitur lanjutan) dan M8 (editor kanvas ala Canva, menunggu K10).
