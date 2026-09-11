# 02 — Quick start

This walks through creating a template, then loading it from code. It takes about five
minutes and assumes the plugin is installed and the migration has run.

> The visual editor is not built yet (planned for M4). Templates are currently written
> as JSON. Everything you write now stays valid once the editor arrives — the editor will
> produce exactly this format.

## 1. Open the designer

Go to **Report Templates** in the panel sidebar, or visit `/admin/report-templates`
directly, and press **New report template**.

## 2. Fill in the identity fields

| Field | Example | Notes |
|---|---|---|
| Title | `Monthly Sales` | Shown in listings |
| Key | `monthly-sales` | Lowercase, hyphen-separated. This is how code calls the report, and it cannot be changed after creation |
| Description | *(optional)* | Free text |

## 3. Write the document

Paste this into the schema editor:

```json
{
    "schema_version": 1,
    "key": "monthly-sales",
    "title": "Monthly Sales",
    "data": {
        "source": "sales"
    },
    "bands": {
        "document_header": [
            { "type": "heading", "level": 1, "content": "Monthly Sales" }
        ],
        "detail": [
            {
                "type": "table",
                "columns": [
                    { "field": "invoice_no", "label": "Invoice" },
                    { "field": "total", "label": "Total", "align": "right" }
                ]
            }
        ]
    }
}
```

Press **Create**.

If anything is wrong, the form reports the exact path that failed — for example
`Unknown band "detials"` — rather than a generic "invalid document" message. Nothing is
saved until the whole document is valid.

### What the bands mean

The `bands` key is what makes this a report rather than a static page. Each band is
rendered at a different point:

| Band | Rendered |
|---|---|
| `document_header` | Once, at the start |
| `page_header` | On every page (paginated output only) |
| `group_header` | Each time a grouping value changes |
| `detail` | **Once per row of data** |
| `group_footer` | At the end of each group — subtotals |
| `page_footer` | On every page (paginated output only) |
| `document_footer` | Once, at the end — grand totals |

Only the bands you actually use need to be present.

## 4. Load the template from code

```php
use ReportBrains\ReportDesigner\TemplateRepository;

$template = app(TemplateRepository::class)->find('monthly-sales');

$template->title;   // "Monthly Sales"
$template->schema;  // the document, as an array
```

## 5. Ship a template as a file instead

Templates can also live in version control, which makes them reviewable in pull requests.
Put the same JSON in `resources/reports/monthly-sales.json`:

```php
$document = app(TemplateRepository::class)->fromFileKey('monthly-sales');
```

File templates are read-only and are validated on every read, exactly like stored ones.

## What comes next

Right now a template is a validated document and nothing more — there is no data binding
and no rendering yet. `data.source` is recorded but not resolved; `{{ ... }}` expressions
are stored verbatim. Those arrive in M2 and M3.
