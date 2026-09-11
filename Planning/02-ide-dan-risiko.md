# 02 — Ide yang Belum Dikerjakan & Risiko

Analisa rencana awal sudah tuntas dan hasilnya terwujud dalam kode — rasionalnya kini ada
di [`Documentation/06-architecture.md`](../Documentation/06-architecture.md). Yang tersisa
di sini hanya yang belum dikerjakan.

## Ide, diurutkan berdasar rasio nilai/biaya

### Layak masuk versi rilis pertama

1. **Preview langsung dengan data sungguhan.** Panel kanan yang me-render HTML tiap
   template berubah. Ini fitur yang paling menentukan rasa "designer" — lebih terasa
   daripada kanvas presisi. Butuh M3 lebih dulu.
2. **Format lokal.** `Rp 1.234.567`, `06/09/2026`, pemisah ribuan. Menyisipkannya
   belakangan berarti menyentuh semua renderer, jadi harus ikut di M3.
3. **Pakai komponen Builder milik Filament untuk editor v1.** `filament/schemas` sudah
   menyediakan Builder dengan drag-drop bawaan, jadi M4 tidak perlu menulis JS sama sekali.
   Kanvas custom baru di M7, setelah nilai produknya terbukti.

### Setelah rilis pertama

4. **Impor Markdown → blok.** Draf report ditulis di MD (atau dihasilkan AI), diimpor jadi
   blok, lalu dirapikan di editor.
5. **Jadwal & pengiriman.** Report jalan tiap Senin pagi, PDF dikirim ke email/Storage.
   Biasanya paling dicari pengguna bisnis dan relatif murah dibangun di atas scheduler.
6. **Grafik** sebagai tipe blok, dirender jadi gambar untuk PDF.
7. **Versioning template** dengan draft/published dan tombol kembali ke versi sebelumnya.

### Dipertimbangkan, bukan janji

8. **Bantuan AI menyusun template.** "Buat laporan penjualan per cabang bulan ini" → JSON.
   Relatif aman diterapkan karena skema tertutup dan tervalidasi — output AI tinggal
   divalidasi terhadap `ReportSchema`. Nilai jual yang kuat untuk produk berbayar.

## Risiko yang perlu diawasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| **Scope creep** — ini pada dasarnya membangun ulang JasperReports | Tidak pernah rilis | Kunci rilis pertama: M2–M4 + MD/HTML. PDF menyusul. |
| Editor drag-drop berat di Livewire | Terasa lambat saat template besar | Alpine + SortableJS di sisi klien, sinkron ke server hanya saat drop |
| Paket PDF belum dukung PHP 8.5 | M5 meleset | Uji `--dry-run` sebelum komit ke satu paket (lihat K3) |
| Sudah ada plugin serupa | Kerja sia-sia | Cek katalog plugin Filament (lihat K6) |
| **Produk dijual, tapi belum ada jalur distribusi** | Tidak bisa menjual meski produk jadi | Siapkan private Packagist/Anystack sebelum M6 |
| Ekspresi `{{ }}` dievaluasi sembarangan | Eksekusi kode jarak jauh di server pembeli | Evaluator tersandbox di M3 — **bukan** Blade, **bukan** `eval` |
