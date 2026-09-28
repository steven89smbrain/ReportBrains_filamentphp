# 02 — Quick start

This walks through designing a report in the panel and loading it from code. It takes about
five minutes and assumes the plugin is installed, the migration has run, and at least one data
source is registered — see [Data sources](08-data-sources.md).

## 0. Want something to look at first?

The development application ships sample sales data and three ready-made templates:

```bash
php artisan migrate
php artisan db:seed --class=DemoReportSeeder
```

This creates 4 branches, 40 customers and 240 orders spread over the last 90 days, registers
them as the **Sales orders** data source, and loads these templates into the designer:

| Template | Shows off |
|---|---|
| **Sales by Branch** | Grouping, subtotals and a grand total; a date range from two parameters, defaulting to the last 30 days |
| **Order List** | An optional *Status* parameter — leave it empty for every status |
| **User Directory** | The smallest possible report |

Open one and use **Try the report with** beside the preview to change the dates or status.
The seeder is safe to run again.

## 1. Open the designer

Go to **Report Templates** in the panel sidebar and press **New report template**.

## 2. Name the report

| Field | Example |
|---|---|
| Title | `User Directory` |
| Key | `user-directory` — how code calls it; cannot be changed later |

## 3. Choose the data

Open the **Data** tab and pick a **Data source**. The preview on the right starts showing
the report as soon as there is something to show.

Optionally sort, group or filter the rows here. A filter can compare with a fixed value or
with a report parameter such as *From date*.

## 4. Lay out the report

Open the **Layout** tab.

1. In **Document header**, add a **Heading** block and type `User Directory`.
2. In **Detail**, add a **Table** block. Add a column per field you want to list — pick the
   field, and its label fills in.
3. In **Document footer**, add a **Text** block, type `Total users:`, then press
   **Insert field**, choose a field and **Count**.

Drag blocks to reorder them. The preview follows every change.

## 5. Save

Press **Create**. If something is wrong the form says what and where, and nothing is saved
until the design is valid.

## 6. Export it

On the template's edit page, press **Export**, choose **PDF**, **HTML** or **Markdown**, and
download. PDF needs a driver installed — see [PDF output](11-pdf-output.md).

## 7. Render it from code

```php
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;
use ReportBrains\ReportDesigner\ReportRunner;
use ReportBrains\ReportDesigner\TemplateRepository;

$document = app(TemplateRepository::class)->find('user-directory')->schema;
$runner = app(ReportRunner::class);

$markdown = $runner->render($document, new MarkdownRenderer);
$pdf = $runner->render($document, app(PdfRenderer::class));
```

## Templates as files

Templates can also live in version control, which makes them reviewable in pull requests.
Design one in the panel, copy the document from the **JSON** tab into
`resources/reports/user-directory.json`, and load it with:

```php
$document = app(TemplateRepository::class)->fromFileKey('user-directory');
```

File templates are read-only and are validated on every read.

## Next

- [The visual designer](10-visual-designer.md) — every tab and block in detail
- [Expressions and rendering](09-expressions-and-rendering.md) — totals, formats, output
