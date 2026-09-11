# 02 — Analisa Rencana Awal & Rekomendasi

Rencana awal yang diajukan:

1. Membuat plugin FilamentPHP untuk mendesain report.
2. Plugin menghasilkan file MD **atau** JSON yang kemudian bisa dipanggil.
3. Editor drag-and-drop yang bisa membaca database jika dikoneksikan.

Poin 1 solid. Poin 2 dan 3 menyimpan dua konflik yang akan mahal kalau baru ketahuan di tengah jalan.

---

## Konflik 1 — "MD atau JSON" memperlakukan dua hal berbeda sebagai setara

**Masalahnya.** JSON dan Markdown bukan dua pilihan format untuk benda yang sama:

- **JSON** bisa menyatakan struktur: kolom, pengulangan per baris data, header/footer halaman, binding
  ke field database, kondisi tampil, format angka.
- **Markdown** tidak bisa menyatakan satu pun dari itu. Tidak ada kolom, tidak ada page break, tidak ada
  header berulang, tidak ada styling, tidak ada konsep "ulangi blok ini untuk tiap baris".

Kalau keduanya diperlakukan sebagai output setara, konsekuensinya: editor harus dibatasi hanya pada
fitur yang MD sanggup tampung (artinya buang layout kolom dan page setup), **atau** ada dua model
dokumen yang perlahan menyimpang dan tidak bisa saling konversi.

**Rekomendasi — pisahkan peran:**

```
Template (JSON)  ──►  Compiler  ──►  Renderer  ──┬──►  Markdown   (.md)
   sumber            + data          per target  ├──►  HTML       (preview)
   kebenaran         binding                     ├──►  PDF        (cetak)
                                                 └──►  XLSX/CSV   (data)
```

- **JSON = sumber kebenaran.** Ini yang disimpan, diedit, diversikan, dan di-diff di git.
- **MD = hasil render**, sejajar dengan PDF dan HTML — bukan format penyimpanan alternatif.

MD tetap sangat berharga sebagai target, justru karena sifatnya: enak dibaca LLM, ringan, bisa di-diff.
Tapi ia hasil akhir, bukan sumber.

**Untuk syarat "bisa dipanggil kembali"**, dukung dua tempat penyimpanan JSON yang sama-sama valid:

| Penyimpanan | Untuk |
|---|---|
| Tabel database | Template yang diedit pengguna lewat UI |
| File `resources/reports/*.json` | Template bawaan aplikasi, ikut git, bisa direview di PR |

Keduanya memuat skema JSON yang identik, dan dipanggil dengan cara yang sama.

---

## Konflik 2 — "drag-and-drop" bisa berarti dua paradigma yang tidak kompatibel

**Masalahnya.** Ada dua model editor report, dan keduanya sama-sama disebut "drag and drop":

| | Flow / berbasis blok | Kanvas absolut |
|---|---|---|
| Analogi | Notion, Gutenberg | Crystal Report, JasperReports |
| Cara kerja | Blok bertumpuk vertikal, bisa bersarang, bisa dibagi kolom | Elemen punya koordinat X/Y presisi |
| Bisa jadi MD? | **Ya** | **Tidak** — koordinat tidak punya padanan di MD |
| Bisa jadi HTML? | Ya, wajar | Perlu positioning absolut |
| Cocok untuk | Laporan naratif, tabel, rekap, dashboard cetak | Faktur presisi, label, formulir pajak, cetak pra-cetak |
| Biaya bangun | Sedang | Tinggi (snapping, ruler, z-index, overlap) |

**Rekomendasi: pilih flow/blok untuk v1.** Alasannya bukan selera — syarat output MD di poin 2
secara teknis *mengharuskan* dokumen tersusun sebagai aliran blok. Kanvas absolut menutup pintu MD
selamanya.

Kanvas absolut bisa ditambahkan belakangan sebagai mode khusus ("Label / Faktur") yang hanya
mengekspor PDF. Jangan dijadikan fondasi.

---

## Konsep yang hilang dari rencana awal: **band**

Inilah yang membedakan *report designer* dari *page builder*. Page builder menyusun satu halaman statis.
Report designer harus tahu bagian mana yang **diulang per baris data**:

```
┌─ Header Dokumen ──── muncul sekali di awal (judul, logo, periode)
├─ Header Halaman ──── muncul di tiap halaman (PDF saja)
│  ┌─ Header Grup ──── tiap ganti nilai grup, mis. per Cabang
│  │  ┌─ Detail ────── DIULANG untuk tiap baris data   ← inti report
│  │  └─ Footer Grup ─ subtotal per grup
├─ Footer Halaman ──── nomor halaman
└─ Footer Dokumen ──── grand total, tanda tangan
```

Tanpa band, yang terbangun cuma editor teks dengan variabel — bukan report designer. Konsep ini harus
masuk skema JSON sejak versi pertama karena mengubahnya belakangan berarti membongkar semua template
yang sudah dibuat pengguna.

---

## Rekomendasi keamanan: **jangan beri UI akses database bebas**

Poin 3 bilang editor "bisa membaca database jika dikoneksikan". Kalau diartikan sebagai UI yang menerima
koneksi/SQL bebas, konsekuensinya berat:

- Template tersimpan di database. Kalau template berisi SQL yang dieksekusi apa adanya, siapa pun yang
  bisa mengedit template otomatis bisa membaca **seluruh** database — termasuk tabel `users`.
- Kalau ekspresi di template dievaluasi sebagai Blade/PHP, itu jalur eksekusi kode jarak jauh: yang bisa
  mengedit report bisa menjalankan perintah di server.

**Rekomendasi — pola registry, di-whitelist di kode:**

```php
// Didaftarkan developer, bukan diketik pengguna di UI
ReportData::source('penjualan', function (SalesSource $s) {
    $s->model(Order::class)
      ->columns(['id', 'invoice_no', 'total', 'created_at'])  // hanya ini yang terlihat editor
      ->relation('customer', ['name', 'city'])
      ->param('dari', 'date')->param('sampai', 'date')
      ->scope(fn ($q) => $q->whereBelongsTo(auth()->user()->tenant));  // isolasi data wajib
});
```

Editor cuma membaca **metadata** (nama field, tipe, relasi) untuk ditawarkan di panel drag-and-drop.
Query tetap dirakit di sisi PHP dari daftar yang sudah diizinkan. Pengguna tidak pernah menulis SQL.

Untuk filter/kondisi, gunakan **`filament/query-builder`** yang sudah ikut terpasang bersama Filament v5 —
UI penyusun kondisi bertingkat sudah jadi, tidak perlu dibangun ulang.

Untuk ekspresi (`{{ total * 1.11 }}`), **jangan pakai `eval` atau render Blade dari string**. Gunakan
evaluator terbatas (`symfony/expression-language`) atau daftar fungsi yang diizinkan
(`sum`, `avg`, `count`, `format_rupiah`, `format_tanggal`, …).

---

## Ide tambahan, diurutkan berdasar rasio nilai/biaya

### Layak masuk v1

1. **Pakai komponen Builder milik Filament dulu.** `filament/schemas` sudah menyediakan Builder dengan
   drag-drop reorder. v1 bisa jalan tanpa menulis satu baris JS pun. Editor kanvas custom baru dikerjakan
   di v2 setelah nilai produknya terbukti. Ini memangkas waktu ke demo pertama dari bulanan jadi mingguan.
2. **Preview langsung dengan data sungguhan.** Panel kanan yang me-render HTML tiap template berubah.
   Ini fitur yang paling menentukan rasa "designer" — lebih terasa daripada kanvas presisi.
3. **Parameter report.** Setiap template mendeklarasikan input (periode, cabang, status). Tanpa ini
   report tidak reusable dan pengguna akan menggandakan template hanya untuk ganti tanggal.
4. **Format lokal Indonesia sejak awal.** `Rp 1.234.567`, `06/09/2026`, nama bulan Indonesia, pemisah
   ribuan titik. Menyisipkannya belakangan berarti menyentuh semua renderer.

### v2

5. **Impor Markdown → blok.** Membuat alur bolak-balik: draf report ditulis di MD (atau dihasilkan AI),
   diimpor jadi blok, lalu dirapikan di editor.
6. **Jadwal & pengiriman.** Report jalan tiap Senin pagi, hasil PDF dikirim ke email/Storage. Ini yang
   biasanya paling dicari pengguna bisnis dan relatif murah dibangun di atas scheduler Laravel.
7. **Grafik** (batang/garis/pai) sebagai tipe blok, dirender jadi gambar untuk PDF.
8. **Versioning template** dengan draft/published, plus tombol kembali ke versi sebelumnya.

### Dipertimbangkan, bukan janji

9. **Bantuan AI menyusun template.** "Buat laporan penjualan per cabang bulan ini" → JSON template.
   Karena JSON adalah sumber kebenaran dan skemanya tertutup, ini relatif aman diterapkan — output AI
   tinggal divalidasi terhadap skema. Repo ini sudah memakai Boost/MCP, jadi jalurnya sudah ada.

---

## Risiko yang perlu diawasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| **Scope creep** — ini pada dasarnya membangun ulang JasperReports | Proyek tidak pernah selesai | Kunci v1: blok, satu sumber data, MD+HTML. PDF menyusul. |
| Editor drag-drop berat di Livewire | Terasa lambat saat template besar | Alpine + SortableJS di sisi klien, sinkron ke server hanya saat drop |
| Paket PDF belum dukung PHP 8.5 | Milestone PDF meleset | Uji `--dry-run` sebelum komit ke satu paket |
| Sudah ada plugin serupa | Kerja sia-sia | Cek katalog plugin Filament dulu; kalau ada yang mirip, pertimbangkan kontribusi |
| Belum ada git | Kehilangan pekerjaan | `git init` sebelum baris kode pertama |
