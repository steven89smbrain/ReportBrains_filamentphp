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

**M0–M4 selesai.** Report bisa dirancang lewat editor visual di panel (drag-and-drop per band,
pilih field berdasarkan label, sisip total tanpa mengetik ekspresi), dipreview langsung dengan data
sungguhan, lalu dirender jadi Markdown atau HTML. 148 test lulus.

**Berikutnya:** putuskan K9 (parameter yang memfilter data), lalu M5 (PDF) dan M6 (distribusi).
