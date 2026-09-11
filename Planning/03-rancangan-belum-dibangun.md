# 03 — Rancangan Bagian yang Belum Dibangun

Struktur package, model penyimpanan, dan validasi skema sudah jadi — dokumentasinya di
[`Documentation/06-architecture.md`](../Documentation/06-architecture.md). Yang di bawah ini
belum ada kodenya.

## Registry sumber data (M2)

Prinsipnya: **UI tidak pernah menerima SQL atau koneksi database bebas.** Template tersimpan
di database; kalau template berisi SQL yang dieksekusi apa adanya, siapa pun yang bisa
mengedit template bisa membaca seluruh database pembeli.

```php
// Didaftarkan developer di kode, bukan diketik pengguna di UI
ReportData::source('sales', function (SourceDefinition $s) {
    $s->model(Order::class)
      ->columns(['id', 'invoice_no', 'total', 'created_at'])  // hanya ini yang terlihat editor
      ->relation('customer', ['name', 'city'])
      ->param('from', 'date')->param('to', 'date')
      ->scope(fn ($q) => $q->whereBelongsTo(auth()->user()->tenant));  // isolasi wajib
});
```

Editor hanya membaca **metadata** (nama field, tipe, relasi) untuk ditawarkan di panel
drag-and-drop. Query dirakit di sisi PHP dari daftar yang sudah diizinkan.

Untuk UI filter, pakai `filament/query-builder` yang sudah ikut terpasang bersama Filament v5
— penyusun kondisi bertingkat sudah jadi, tidak perlu dibangun ulang.

`data.source` di skema saat ini menerima string apa pun. Setelah M2, nilai yang tidak
terdaftar harus ditolak validator.

## Compiler (M3)

Mengubah dokumen + data jadi pohon yang sudah matang:

```
Dokumen (JSON) ─┐
                ├─► Compiler ─► Pohon terender ─► Renderer
Baris data ─────┘
```

Tugasnya: mengulang band `detail` per baris, memecah per grup untuk `group_header`/
`group_footer`, mengevaluasi ekspresi, dan menghitung agregat.

Pohon hasilnya **murni data** — tanpa ekspresi, tanpa binding, tanpa akses database.
Akibatnya renderer bisa dites tanpa database sama sekali.

## Evaluator ekspresi (M3)

`{{ ... }}` saat ini disimpan apa adanya dan tidak pernah dievaluasi. Saat dibangun nanti:

- **Bukan Blade, bukan `eval`.** Dokumen adalah masukan pengguna; mengevaluasinya sebagai
  kode berarti jalur eksekusi kode jarak jauh di server pembeli.
- Pakai evaluator terbatas (`symfony/expression-language`) atau daftar fungsi yang
  diizinkan: `sum`, `avg`, `count`, `min`, `max`, plus formatter.
- Tidak ada akses ke `$this`, facade, atau fungsi PHP sembarangan.

Bentuk yang direncanakan: `{{ params.from }}`, `{{ total }}`, `{{ customer.name }}`,
`{{ group.value }}`, `{{ sum(total) }}`.

## Renderer (M3, M5, M6)

Satu kelas per target, semuanya membaca pohon terender yang sama:

| Renderer | Milestone | Catatan |
|---|---|---|
| Markdown | M3 | Abaikan `page`; tabel jadi tabel MD |
| HTML | M3 | Dipakai juga untuk preview di editor |
| PDF | M5 | Perlu `page`, header/footer halaman, nomor halaman |
| XLSX/CSV | M6 | `openspout` sudah ikut terpasang bersama Filament |

## API runtime (M6)

```php
$pdf = Report::make('monthly-sales')
    ->params(['from' => '2026-09-01', 'to' => '2026-09-30'])
    ->toPdf();

$md = Report::make('monthly-sales')->params([...])->toMarkdown();

Report::fromFile(resource_path('reports/monthly-sales.json'))->toHtml();

ReportJob::dispatch('monthly-sales', $params, $user);  // report besar
```

```bash
php artisan report:render monthly-sales --param=from=2026-09-01 --format=md
```

## Keamanan yang masih harus ditangani

Yang sudah ditangani ada di
[`Documentation/06-architecture.md`](../Documentation/06-architecture.md#security-boundaries).
Sisanya:

| Ancaman | Rencana penanganan | Milestone |
|---|---|---|
| Pengguna mengakses tabel di luar haknya | Registry whitelist + `scope()` wajib | M2 |
| SQL injection lewat template | Query dirakit dari field terdaftar; nilai selalu binding | M2 |
| RCE lewat ekspresi template | Evaluator tersandbox | M3 |
| XSS di preview HTML | Escape semua nilai data; blok HTML mentah dimatikan bawaan | M3 |
| Report berat membebani server | Queue, batas jumlah baris, timeout | M6 |
