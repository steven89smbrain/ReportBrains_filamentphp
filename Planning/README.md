# Planning — pekerjaan yang belum dikerjakan

Folder ini hanya memuat **yang belum selesai**. Pekerjaan yang sudah jadi pindah ke
[`Documentation/`](../Documentation/README.md) — lihat
[changelog](../Documentation/07-changelog.md) untuk apa saja yang sudah rampung.

Dokumen di sini sengaja tetap berbahasa Indonesia: ini catatan kerja internal, bukan
bagian dari produk yang dijual. Semua yang menghadap pengguna (UI plugin dan
`Documentation/`) berbahasa Inggris.

| Dokumen | Isi |
|---|---|
| [02-ide-dan-risiko.md](02-ide-dan-risiko.md) | Ide yang belum dikerjakan dan risiko yang perlu diawasi |
| [03-rancangan-belum-dibangun.md](03-rancangan-belum-dibangun.md) | Rancangan compiler, renderer, sumber data, API runtime |
| [04-roadmap.md](04-roadmap.md) | Milestone M2–M7 |
| [05-keputusan-terbuka.md](05-keputusan-terbuka.md) | Keputusan yang masih menggantung |

## Posisi saat ini

**M0–M4 dan K9 selesai.** Report dirancang lewat editor visual, filternya bisa digerakkan
parameter (termasuk rentang tanggal), dipreview langsung dengan data sungguhan, lalu dirender jadi
Markdown atau HTML. Aplikasi demo punya data contoh penjualan dan tiga template siap pakai.
176 test lulus.

**Berikutnya:** M5 (PDF) — tapi putuskan K3 (paket PDF) dulu — dan M6 (distribusi).
