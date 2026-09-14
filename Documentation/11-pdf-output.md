# 11 — PDF output

Reports print to PDF from the same HTML the designer previews. What you see beside the
designer is what you get on paper.

## How it works

`PdfRenderer` turns a compiled report into a page stylesheet plus body HTML, and hands it to
[spatie/laravel-pdf](https://github.com/spatie/laravel-pdf), which prints it with the
**driver** you choose. The plugin does not lock you to one engine.

## Choosing a driver

Every driver below is open source and free to use — no subscription and no hosted service.

| Driver | Engine | Needs on the server | Matches the preview | Licence |
|---|---|---|---|---|
| **`chrome`** — recommended | Chromium | Chrome or Chromium, and `chrome-php/chrome` | Yes | MIT |
| `browsershot` | Chromium | Node.js, Puppeteer, and `spatie/browsershot` | Yes | MIT |
| `gotenberg` | Chromium | A running [Gotenberg](https://gotenberg.dev) container | Yes | MIT |
| `dompdf` | Its own, CSS 2.1 | `dompdf/dompdf` only | **No** — limited CSS | LGPL-2.1 |

### Why Chromium

The designer runs in a browser. A PDF printed by a browser engine lays pages out the same
way — the same fonts, table widths, alignment, and modern CSS such as flexbox, grid,
transforms and absolute positioning. An engine with its own layout rules, such as dompdf,
produces output that visibly drifts from the preview, and the gap widens as layouts get more
designed. `dompdf` remains available where nothing can be installed, with that trade-off.

laravel-pdf also offers a Cloudflare driver. It is a paid hosted service, so it is not
recommended here.

## Installing a driver

### Chrome (recommended)

```bash
composer require chrome-php/chrome
```

Install Google Chrome or Chromium on the server. The binary is found automatically in its
usual locations; point to it explicitly if needed:

```dotenv
LARAVEL_PDF_CHROME_BINARY=/usr/bin/chromium
```

Running as root or inside a container, Chrome usually needs its sandbox disabled:

```dotenv
LARAVEL_PDF_CHROME_NO_SANDBOX=true
```

### Browsershot

```bash
composer require spatie/browsershot
npm install puppeteer
```

```dotenv
REPORT_DESIGNER_PDF_DRIVER=browsershot
```

### Gotenberg

Run the container, then point laravel-pdf at it:

```bash
docker run --rm -p 3000:3000 gotenberg/gotenberg:8
```

```dotenv
REPORT_DESIGNER_PDF_DRIVER=gotenberg
GOTENBERG_URL=http://localhost:3000
```

Useful when the application server cannot run a browser itself.

### dompdf

```bash
composer require dompdf/dompdf
```

```dotenv
REPORT_DESIGNER_PDF_DRIVER=dompdf
```

All driver settings — binary paths, timeouts, Gotenberg credentials — live in laravel-pdf's own
config. Publish it with:

```bash
php artisan vendor:publish --tag=pdf-config
```

## Page setup

The document's `page` settings map straight onto the printed page:

| Setting | Default |
|---|---|
| `page.size` — `A3`, `A4`, `A5`, `Letter`, `Legal` | `A4` |
| `page.orientation` — `portrait`, `landscape` | `portrait` |
| `page.margin.top/right/bottom/left`, in millimetres | 15 mm each |

When a page header or footer is used and its margin is not set, that margin defaults to
25 mm instead. Chrome draws headers and footers inside the margin, so the default would clip
them.

Tables that span pages repeat their header row on every page, and rows are not split across
a page break.

## Page headers, footers and page numbers

The **Page header** and **Page footer** bands print on every page. They only appear in PDF
output — Markdown and HTML have no pages.

Two expressions are available in those bands, and only there:

| Expression | Prints |
|---|---|
| `{{ page.number }}` | The current page number |
| `{{ page.total }}` | The total number of pages |

```json
"page_footer": [
    { "type": "text", "content": "Page {{ page.number }} of {{ page.total }}", "align": "center" }
]
```

In the designer, **Insert field** offers both. Using either in any other band is refused with
an explanation, because page numbers do not exist until the PDF is laid out.

## Exporting from the panel

The template's edit page has an **Export** action. Choose PDF, HTML or Markdown, fill in the
report's parameters, and download. It runs the **saved** template against live data;
unsaved changes are not included.

If the PDF driver fails — Chrome missing, a sandbox restriction, Gotenberg unreachable — the
reason is shown in a notification rather than as an error page.

## From code

`ReportRunner` runs a document end to end:

```php
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;
use ReportBrains\ReportDesigner\ReportRunner;
use ReportBrains\ReportDesigner\TemplateRepository;

$document = app(TemplateRepository::class)->find('sales-by-branch')->schema;

$pdf = app(ReportRunner::class)->render($document, app(PdfRenderer::class), [
    'from' => '2026-09-01',
    'to' => '2026-09-30',
]);

Storage::put('reports/sales-september.pdf', $pdf);
```

`PdfRenderer::builder()` returns the configured laravel-pdf builder before printing, if you
want to stream it, queue it or add metadata yourself.

## Testing

`PdfRenderer` prints through laravel-pdf's facade, so its fake works:

```php
use Spatie\LaravelPdf\Facades\Pdf;

Pdf::fake();

app(ReportRunner::class)->render($document, app(PdfRenderer::class));

Pdf::assertSee('Grand total');
```

No browser is started while faked.
