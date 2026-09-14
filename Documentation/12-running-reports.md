# 12 — Running reports from code

The `Report` facade runs a template and turns it into a file, a download or a queued job.

```php
use ReportBrains\ReportDesigner\Facades\Report;

$pdf = Report::template('sales-by-branch')
    ->with(['from' => '2026-09-01', 'to' => '2026-09-30'])
    ->toPdf();
```

## Choosing the template

| Method | Loads |
|---|---|
| `Report::template('sales-by-branch')` | A template stored in the database, honouring ownership and tenancy |
| `Report::file('sales-by-branch')` | `resources/reports/sales-by-branch.json` |
| `Report::file(resource_path('reports/custom.json'))` | A JSON file inside the configured template directory |
| `Report::document($array)` | A document built in code — validated before anything runs |

Each returns a `PendingReport`. Nothing touches the data source until you ask for output.

## Parameters

```php
$report = Report::template('order-list')->with(['status' => 'paid']);
$recent = $report->with(['from' => now()->subWeek()->toDateString()]);
```

`with()` returns a new report and merges over earlier parameters; the original is unchanged.
Parameters are validated against those the data source declares — see
[Data sources](08-data-sources.md#parameters).

## Output formats

| Format | Method | Notes |
|---|---|---|
| `pdf` | `toPdf()` | Needs a PDF driver — see [PDF output](11-pdf-output.md) |
| `html` | `toHtml()` | A complete HTML document |
| `markdown` | `toMarkdown()` | |
| `csv` | `toCsv()` | Data rows only — see [Spreadsheets](#spreadsheets) |
| `xlsx` | `toXlsx()` | Data rows only — see [Spreadsheets](#spreadsheets) |

`render('xlsx')` does the same by name. An unknown name throws `UnsupportedFormat`, listing the
formats that exist.

### Saving

```php
Report::template('sales-by-branch')->save('reports/september.pdf');
Report::template('sales-by-branch')->save('september.xlsx', disk: 's3');
Report::template('sales-by-branch')->save('export.txt', format: 'csv');
```

The format follows the file extension (`.md` means Markdown, `.htm` HTML) unless given, and
falls back to PDF. `save()` returns the path.

### Downloading

```php
Route::get('/reports/sales', fn () => Report::template('sales-by-branch')->download(format: 'xlsx'));
```

The filename defaults to `{template-key}-{date}.{extension}`.

### Adding a format

Register a renderer and it becomes available everywhere a format is chosen: this API, the
panel's **Export** action and `report:render`.

```php
use ReportBrains\ReportDesigner\Facades\Report;

Report::formats()->register('docx', 'Word', fn () => new DocxRenderer);
```

A renderer implements `ReportBrains\ReportDesigner\Renderers\Contracts\Renderer`:
`render(RenderedReport $report): string`, `extension()` and `mimeType()`.

## Spreadsheets

CSV and XLSX hold data, not layout, so they differ from the document formats:

- **Only tables in the Detail band are exported.** Headings, text, totals and tables in other
  bands are left out.
- **Values are raw.** A total stays `1250.5`, not `$1,250.50`, so the spreadsheet can sum and sort
  it. In XLSX, numbers and dates are stored as real numbers and dates.
- **Grouped reports get a leading `Group` column**, so the grouping survives sorting and filtering.
- **CSV starts with a byte-order mark**, so Excel reads accented characters correctly.

Both guard against formula injection, because report data comes from your database and anyone
might have typed `=HYPERLINK(...)` into a field:

- **CSV** prefixes text that starts with `=`, `+`, `-`, `@`, a tab or a carriage return with an
  apostrophe. Numbers, including negative ones, are untouched.
- **XLSX** stores all text as text cells, so it is never evaluated as a formula.

## Queueing large reports

```php
Report::template('sales-by-branch')
    ->with(['from' => '2026-01-01', 'to' => '2026-12-31'])
    ->queue('reports/sales-2026.xlsx', disk: 's3');
```

The report is rendered by a queue worker and stored at the path.

**It runs as the user who queued it.** Data source scopes usually depend on who is signed in, and
nobody is signed in on a queue worker, so the job signs in as the requester before reading any
data. Pass `as:` to run as someone else. If that user has been deleted by the time the job runs,
the job fails rather than reading data with no scope applied.

The document is captured when you call `queue()`, so editing the template while the job waits
does not change what is rendered.

When the file is stored, `ReportBrains\ReportDesigner\Events\ReportRendered` is dispatched with
`templateKey`, `format`, `path`, `disk` and `userId` — listen for it to notify the person who asked:

```php
Event::listen(ReportRendered::class, function (ReportRendered $event) {
    User::find($event->userId)?->notify(new ReportReady($event->path, $event->disk));
});
```

## The `report:render` command

```bash
php artisan report:render sales-by-branch --output=reports/september.pdf \
    --param=from=2026-09-01 --param=to=2026-09-30
```

| Option | Meaning |
|---|---|
| `template` | A stored template key, a file template key, or a path to a `.json` template. A stored template wins over a file with the same key |
| `--format` | `pdf`, `html`, `markdown`, `csv`, `xlsx`, or a registered format. Defaults to the `--output` extension, then PDF |
| `--output` | Where to store it. Defaults to `reports/{key}-{date-time}.{extension}` |
| `--disk` | Filesystem disk. Defaults to the default disk |
| `--param=name=value` | A parameter. Repeat for more |
| `--as` | The ID of the user to run as |
| `--queue` | Queue the render instead of running it now |

**Nobody is signed in on the command line.** If a data source's scope depends on the signed-in
user, pass `--as`; otherwise the scope sees no user.

Schedule a report like any other command:

```php
Schedule::command('report:render sales-by-branch --output=reports/weekly.pdf --as=1')->weeklyOn(1, '07:00');
```
