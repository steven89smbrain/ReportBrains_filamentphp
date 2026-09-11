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

**M0 dan M1 selesai.** Template report bisa ditulis, divalidasi, disimpan, dan dibaca
kembali — lewat panel maupun dari file JSON. 38 test lulus.

**Berikutnya: M2 — sumber data.** Registry whitelist, introspeksi skema, perakit query,
dan isolasi data. Inilah yang membuat template mulai terhubung ke data sungguhan.
