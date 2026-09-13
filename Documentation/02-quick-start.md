# 02 — Quick start

This walks through designing a report in the panel and loading it from code. It takes about
five minutes and assumes the plugin is installed, the migration has run, and at least one data
source is registered — see [Data sources](08-data-sources.md).

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

Optionally sort, group or filter the rows here.

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

## 6. Render it from code

```php
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use ReportBrains\ReportDesigner\TemplateRepository;

$document = app(TemplateRepository::class)->find('user-directory')->schema;

$query  = app(ReportQueryFactory::class)->make($document, []);
$rows   = app(ReportQueryFactory::class)->sourceFor($document)->rows($query);
$report = app(ReportCompiler::class)->compile($document, $rows, $query->parameters);

$markdown = (new MarkdownRenderer)->render($report);
```

A single call for this sequence arrives in M6.

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
