# 09 — Expressions and rendering

Once a document is bound to a data source, the compiler turns it into a fully resolved tree
and a renderer writes that out. This page covers both, plus the `{{ ... }}` expression
language.

## Expressions

### Why it is deliberately tiny

Report documents are user input stored in the database. Rendering them through Blade, or
evaluating them with `eval`, would mean anyone who can edit a template can run code on the
server — including on your customers' servers.

So this is not a general expression language. It understands exactly three forms and refuses
everything else. There is no arithmetic, no nesting, no method calls, and no way to reach the
container, a facade or the request.

### The three forms

```
{{ total }}                  a field on the current row
{{ params.from }}            a report parameter
{{ group.value }}            the current group's value
{{ sum(total) }}             an aggregate over the rows in scope
{{ sum(total) | currency }}  any of the above, formatted
```

A field name containing a dot (`customer.name`) is matched as a whole name, not traversed,
so relation fields work as written.

### Aggregate functions

| Function | Notes |
|---|---|
| `sum(field)` | Non-numeric values ignored. `0` when no rows are in scope |
| `avg(field)` | Non-numeric values ignored |
| `count(field)` | Counts non-null values |
| `min(field)`, `max(field)` | Numeric values only |

**Scope matters.** In `group_footer`, an aggregate covers that group's rows. In
`document_footer`, it covers every row. That is what makes subtotals and grand totals work
from the same expression.

### Formatters

| Formatter | Example output |
|---|---|
| `currency` | `$1,234.56` |
| `number` | `1,234.56` |
| `integer` | `1,235` |
| `percent` | `12.50%` |
| `date` | `2026-09-11` |
| `datetime` | `2026-09-11 14:30` |
| `upper`, `lower` | case conversion |

Table columns can use the same names in their `format` key, which is usually clearer than an
expression.

### What is refused

An unknown function, an unknown formatter, or a reference that matches no field, parameter
or group value throws `InvalidExpression`. A reference that matches nothing is treated as a
typo in the template rather than as missing data — reporting it is better than rendering a
silently empty cell in a financial report.

## Formatting configuration

Number and date formatting come from config rather than `ext-intl`, which is not guaranteed
on a buyer's server. Output therefore does not change with the host's PHP build:

```php
'formatting' => [
    'decimals' => 2,
    'decimal_separator' => '.',
    'thousands_separator' => ',',
    'currency_symbol' => '$',
    'currency_symbol_after' => false,
    'currency_decimals' => 2,
    'percent_suffix' => '%',
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d H:i',
],
```

For Indonesian formatting (`Rp 1.234.567`, `11/09/2026`):

```php
'currency_symbol' => 'Rp ',
'currency_decimals' => 0,
'decimal_separator' => ',',
'thousands_separator' => '.',
'date_format' => 'd/m/Y',
```

## How bands are compiled

| Band | Rendered | Aggregate scope |
|---|---|---|
| `document_header` | Once | All rows |
| `group_header` | Once per group | That group's rows |
| `detail` | See below | That group's rows, or all rows when ungrouped |
| `group_footer` | Once per group | That group's rows |
| `document_footer` | Once | All rows |

### The detail band

Two rules, because two kinds of block behave differently:

- A **`table` block consumes the whole row set** — one table with every row, not one table
  per row. A table is already a repeating structure.
- **Every other block repeats once per row.** A `text` block in `detail` produces one
  paragraph per row.

### Grouping

`data.group_by` splits rows by a field, and each group gets its own header, detail and
footer bands in document order.

> **Limitation in v1:** one grouping level. A document with more than one entry in
> `group_by` throws `UnsupportedFeature` rather than silently ignoring the extras — a report
> that quietly drops a grouping level is worse than one that refuses to run.

## Rendering

```php
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;

$report = app(ReportCompiler::class)->compile($document, $rows, $parameters);

echo (new MarkdownRenderer)->render($report);
```

The compiled `RenderedReport` is pure data — no expressions, no field references, no
database access. Renderers only translate, which is why adding an output format means
writing one class and touching nothing else, and why renderers can be tested without a
database.

### Markdown

Page setup and the page header/footer bands are skipped: Markdown has no pages, and
inventing a representation for them would produce output that looks broken rather than
merely plain.

Data is escaped, including the pipe character — a customer name containing `|` cannot split
a table column.

### HTML

Used for the designer preview, and later as the input to PDF.

```php
new HtmlRenderer;                  // full document with a stylesheet
new HtmlRenderer(fragment: true);  // markup only, for embedding
```

**Every value is escaped.** Report data comes from the host application's database, and a
customer name containing a script tag must not become script in someone else's browser.

Each band is wrapped in `<section class="rb-band rb-band--detail">`, so a host application
can restyle bands without the renderer knowing about it.

## Putting it together

```php
$document = app(TemplateRepository::class)->find('orders-by-branch')->schema;

$query = app(ReportQueryFactory::class)->make($document, ['min_total' => 100]);
$rows  = app(ReportQueryFactory::class)->sourceFor($document)->rows($query);

$report = app(ReportCompiler::class)->compile($document, $rows, $query->parameters);

$markdown = (new MarkdownRenderer)->render($report);
```

A single façade for this sequence arrives in M6.
