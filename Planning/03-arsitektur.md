# 03 — Arsitektur

## Struktur package

Karena target akhirnya adalah **plugin yang bisa dipasang orang lain**, kodenya dipisah dari aplikasi
sejak awal — bukan ditaruh di `app/`. Aplikasi ini berperan sebagai lingkungan pengembangan sekaligus
contoh pemakaian.

```
Report-FilamentPhp/            ← aplikasi host (untuk develop & demo)
├── app/
├── resources/reports/         ← template JSON bawaan aplikasi (ikut git)
└── packages/
    └── filament-report-designer/     ← package plugin sesungguhnya
        ├── composer.json
        ├── src/
        │   ├── ReportDesignerPlugin.php      ← entry point Filament
        │   ├── ReportDesignerServiceProvider.php
        │   ├── Schema/                       ← definisi & validasi skema JSON
        │   ├── Blocks/                       ← tipe blok: Text, Table, Group, Chart…
        │   ├── DataSources/                  ← registry + introspeksi metadata
        │   ├── Compiler/                     ← template + data → pohon terender
        │   ├── Renderers/                    ← Markdown, Html, Pdf, Xlsx
        │   ├── Filament/                     ← Resource, Page editor, komponen
        │   └── Facades/Report.php            ← API pemanggilan
        ├── database/migrations/
        ├── resources/views/
        └── tests/
```

Dihubungkan lewat path repository di `composer.json` aplikasi, jadi perubahan langsung terasa tanpa
publish ke Packagist:

```json
"repositories": [{ "type": "path", "url": "packages/filament-report-designer" }]
```

## Alur data

```
                    ┌──────────────────────┐
   Editor Filament ─┤  Template (JSON)     ├─ disimpan di DB atau file
                    └──────────┬───────────┘
                               │
        Parameter runtime ─────┤   (periode, cabang, dsb)
                               ▼
                    ┌──────────────────────┐
                    │  DataSource Registry │  ← whitelist di kode
                    │  merakit query aman  │
                    └──────────┬───────────┘
                               ▼
                    ┌──────────────────────┐
                    │      Compiler        │  band diulang per baris,
                    │  binding + ekspresi  │  ekspresi dievaluasi (sandbox),
                    └──────────┬───────────┘  agregat dihitung
                               ▼
                    ┌──────────────────────┐
                    │   Pohon terender     │  murni data, tanpa logika
                    └──────────┬───────────┘
              ┌────────────────┼────────────────┬──────────────┐
              ▼                ▼                ▼              ▼
          Markdown           HTML              PDF          XLSX/CSV
```

Kunci desain: **Compiler menghasilkan pohon yang sudah "matang"** — tanpa ekspresi, tanpa binding,
tanpa akses database. Renderer hanya menerjemahkan pohon itu ke sintaks target. Konsekuensinya:
menambah format ekspor baru = menulis satu renderer, tidak menyentuh logika data. Dan renderer bisa
dites tanpa database sama sekali.

## Skema JSON — rancangan awal

```jsonc
{
  "schema_version": 1,
  "key": "penjualan-bulanan",
  "title": "Laporan Penjualan Bulanan",

  "params": [
    { "name": "dari",   "type": "date", "label": "Dari Tanggal", "required": true },
    { "name": "sampai", "type": "date", "label": "Sampai Tanggal", "required": true },
    { "name": "cabang", "type": "select", "source": "cabang", "label": "Cabang" }
  ],

  "data": {
    "source": "penjualan",              // harus terdaftar di registry
    "filters": [ /* format filament/query-builder */ ],
    "sort":    [ { "field": "created_at", "dir": "desc" } ],
    "group_by": ["cabang.nama"]
  },

  "page": {                              // hanya dipakai renderer PDF
    "size": "A4", "orientation": "portrait",
    "margin": { "top": 20, "right": 15, "bottom": 20, "left": 15 }
  },

  "bands": {
    "document_header": [
      { "type": "heading", "level": 1, "content": "Laporan Penjualan" },
      { "type": "text",    "content": "Periode {{ params.dari }} s/d {{ params.sampai }}" }
    ],
    "group_header": [
      { "type": "heading", "level": 2, "content": "Cabang: {{ group.value }}" }
    ],
    "detail": [
      { "type": "table",
        "columns": [
          { "field": "invoice_no", "label": "No. Faktur", "width": "20%" },
          { "field": "customer.name", "label": "Pelanggan" },
          { "field": "total", "label": "Total", "align": "right", "format": "rupiah" }
        ] }
    ],
    "group_footer": [
      { "type": "text", "content": "Subtotal: {{ sum(total) }}", "align": "right", "bold": true }
    ],
    "document_footer": [
      { "type": "text", "content": "Grand Total: {{ sum(total) }}", "align": "right" }
    ]
  }
}
```

Catatan rancangan:

- **`schema_version` wajib ada sejak hari pertama.** Tanpa itu, template lama tidak bisa dimigrasikan
  saat skema berubah, dan skema *pasti* berubah.
- **`bands` dipisah dari `data`** supaya tata letak dan sumber data bisa diubah independen.
- **Ekspresi `{{ }}` bukan Blade.** Ia dievaluasi evaluator terbatas dengan daftar fungsi yang
  diizinkan. Tidak ada akses ke `$this`, facade, atau fungsi PHP sembarangan.
- Setiap blok punya `type` yang memetakan ke satu kelas di `src/Blocks/`, masing-masing tahu cara
  merender dirinya ke tiap target.

## Cara memanggil report

```php
// Dari kode
$pdf = Report::make('penjualan-bulanan')
    ->params(['dari' => '2026-09-01', 'sampai' => '2026-09-30'])
    ->toPdf();

$md = Report::make('penjualan-bulanan')->params([...])->toMarkdown();

// Dari file, bukan database
Report::fromFile(resource_path('reports/penjualan-bulanan.json'))->toHtml();

// Untuk report besar — jangan blokir request
ReportJob::dispatch('penjualan-bulanan', $params, $user);
```

```bash
php artisan report:render penjualan-bulanan --param=dari=2026-09-01 --format=md
```

## Keamanan — daftar periksa

| Ancaman | Penanganan |
|---|---|
| Pengguna mengakses tabel di luar haknya | Registry whitelist + `scope()` wajib per sumber data |
| SQL injection lewat template | Query dirakit dari daftar field terdaftar; nilai selalu jadi binding |
| RCE lewat ekspresi template | Evaluator sandbox, bukan `eval`/Blade dari string |
| XSS di preview HTML | Escape semua nilai data; blok HTML mentah dimatikan secara default |
| Path traversal saat baca template file | Batasi ke direktori `resources/reports`, tolak `..` |
| Report berat membebani server | Jalankan lewat queue, batasi jumlah baris, timeout |
| Kebocoran antar tenant | `scope()` wajib; uji dengan test yang khusus mengecek isolasi tenant |

## Rencana pengujian

Pengujian dipermudah oleh pemisahan Compiler/Renderer:

- **Unit** — validasi skema, evaluator ekspresi (termasuk kasus jahat), setiap renderer diuji dengan
  pohon terender buatan, tanpa database.
- **Feature** — end-to-end template → data seed → MD/HTML, plus uji keamanan: sumber data tak terdaftar
  harus ditolak, scope tenant harus tidak bisa ditembus.
- **Snapshot** — bandingkan output MD/HTML dengan berkas acuan supaya perubahan renderer ketahuan.

Jalankan dengan `php artisan test`. Karena `RefreshDatabase` masih dinonaktifkan di `tests/Pest.php`,
aktifkan sebelum menulis test yang menyentuh database.
