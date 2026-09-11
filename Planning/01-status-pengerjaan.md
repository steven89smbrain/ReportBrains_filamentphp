# 01 — Status Pengerjaan

Diperiksa: 2026-09-06. Semua angka di bawah hasil pemeriksaan langsung, bukan asumsi.

## Progres plugin: 0%

Belum ada satu baris pun kode report designer. Yang ada baru fondasi Laravel.

## Yang sudah ada

| Komponen | Versi / kondisi | Status |
|---|---|---|
| PHP | 8.5.8 (Herd) | ✅ |
| Laravel Framework | 13.30.1 | ✅ |
| Database | SQLite (`database/database.sqlite`), 3 migrasi bawaan sudah jalan | ✅ |
| Testing | Pest 5 + `laravel/pao` (output JSON ringkas), 2 test lulus | ✅ |
| Frontend | Vite 8 + Tailwind v4 (konfigurasi CSS-first) | ✅ |
| Laravel Boost | v2.7.0 — guidelines, 4 skill, MCP server | ✅ |
| Dokumentasi agen | `CLAUDE.md` + `AGENTS.md` (tersinkron) | ✅ |

Isi `app/` masih bawaan: `User`, `Controller` abstrak, `AppServiceProvider`. `routes/web.php` hanya satu
route ke `welcome.blade.php`.

## Yang belum ada

- [ ] **Filament belum terpasang** — tidak ada `filament/*` di `composer.json`, tidak ada panel provider
- [ ] Package plugin (`packages/…`) belum dibuat
- [ ] Model + migrasi untuk menyimpan template report
- [ ] Skema JSON dokumen report
- [ ] Editor drag-and-drop
- [ ] Registry sumber data & introspeksi skema
- [ ] Renderer (MD / HTML / PDF)
- [ ] API runtime untuk memanggil report
- [ ] Test untuk semua di atas

## Temuan yang perlu ditangani sebelum mulai koding

### 1. Repo ini bukan git repository — prioritas tinggi

`git status` gagal: tidak ada `.git`. Membangun plugin tanpa version control berisiko; regenerasi Boost
sempat menghapus 63 baris `CLAUDE.md` dan hanya bisa dipulihkan karena ada backup manual.

```bash
git init && git add -A && git commit -m "Initial commit: Laravel 13 skeleton + Boost"
```

### 2. Nama folder menjanjikan Filament, isinya belum

Direktori bernama `Report-FilamentPhp` tapi Filament belum ada. Tidak ada konvensi Filament yang bisa
diikuti — semua keputusan struktur masih terbuka.

### 3. Kompatibilitas Filament sudah diverifikasi

`composer require filament/filament:^5.0 --dry-run` **berhasil resolve** di Laravel 13.30.1 / PHP 8.5.8:

- `filament/filament` v5.7.8, `livewire/livewire` v4.4.3
- Ikut terbawa dan relevan untuk proyek ini: **`filament/schemas`** (komponen Builder/Repeater dengan
  drag-drop bawaan), **`filament/query-builder`** (UI penyusun filter bertingkat),
  `ueberdosis/tiptap-php` (rich text), `openspout/openspout` + `league/csv` (ekspor spreadsheet)

Tiga paket pertama memangkas banyak pekerjaan editor dan filter yang tadinya harus dibuat dari nol.

### 4. Stack sangat baru — risiko ekosistem

Laravel 13 dan PHP 8.5 tergolong baru. Paket pihak ketiga (terutama renderer PDF) belum tentu mendukung.
Setiap dependensi baru wajib diuji dengan `--dry-run` dulu, seperti yang dilakukan pada Filament.
