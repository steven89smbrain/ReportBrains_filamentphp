# Planning — Filament Report Designer

Folder ini berisi dokumen perencanaan plugin **report designer untuk FilamentPHP**.

| Dokumen | Isi |
|---|---|
| [01-status-pengerjaan.md](01-status-pengerjaan.md) | Kondisi repo saat ini, apa yang sudah ada, apa yang belum |
| [02-analisa-dan-rekomendasi.md](02-analisa-dan-rekomendasi.md) | Analisa 3 poin rencana awal + rekomendasi perubahan |
| [03-arsitektur.md](03-arsitektur.md) | Struktur package, skema JSON, alur render, keamanan |
| [04-roadmap.md](04-roadmap.md) | Milestone M0–M7 dengan definition of done |
| [05-keputusan-terbuka.md](05-keputusan-terbuka.md) | Keputusan yang masih perlu dijawab sebelum coding |

## Ringkasan eksekutif

**Status:** belum ada kode plugin sama sekali. Repo masih skeleton Laravel 13.30.1 + Laravel Boost.
Filament belum terpasang. Progres plugin: **0%**.

**Tiga rekomendasi terbesar** (detail di [02](02-analisa-dan-rekomendasi.md)):

1. **JSON jadi satu-satunya sumber kebenaran, Markdown jadi salah satu exporter** — bukan dua format
   setara. MD tidak bisa menyatakan kolom, page break, header/footer berulang, atau styling; kalau
   diperlakukan setara, akan lahir dua model yang saling menyimpang.
2. **Editor berbasis blok (flow), bukan kanvas absolut** — karena syarat "menghasilkan MD" hanya mungkin
   kalau dokumen tersusun sebagai aliran blok. Kanvas koordinat-absolut ala Crystal Report hanya bisa
   diekspor ke PDF.
3. **Sumber data lewat registry yang di-whitelist di kode, bukan koneksi database bebas dari UI** —
   editor membaca *metadata* skema, bukan mengeksekusi SQL karangan pengguna.

**Keputusan teknis yang sudah terverifikasi:** target **Filament v5** (v5.7.8 resolve bersih di stack ini).
