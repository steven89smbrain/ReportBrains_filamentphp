# 06 — Architecture

## The central decision: JSON is the source of truth

A report can be output as Markdown, HTML, PDF or a spreadsheet. Those are **renderings**,
not storage formats. Only JSON can express what a report actually needs — columns, page
breaks, repeating bands, data binding, conditional display — so the JSON document is what
gets stored, edited, versioned and diffed:

```
Document (JSON)  ──►  Compiler  ──►  Renderer  ──┬──►  Markdown
  source of            + data        per target  ├──►  HTML
  truth                binding                   ├──►  PDF
                                                 └──►  XLSX / CSV
```

Treating Markdown as an equal storage format would force a choice between crippling the
editor down to what Markdown can express, or maintaining two models that slowly diverge and
cannot be converted into one another.

**The compiler produces a fully resolved tree** — no expressions, no bindings, no database
access left in it. Renderers only translate that tree into their target syntax. Two things
follow: adding an output format means writing one renderer and touching nothing else, and
renderers can be tested without a database.

## Package layout

The plugin is the product; the surrounding Laravel application is only a harness for
developing and demonstrating it.

```
packages/filament-report-designer/
├── config/report-designer.php      Ownership, tenancy, preview, formatting, file paths
├── database/migrations/            The report_templates table
└── src/
    ├── ReportDesignerPlugin.php    Filament entry point, plugin options
    ├── ReportDesignerServiceProvider.php
    ├── TemplateRepository.php      Loads documents from the database or disk
    ├── ReportRunner.php            Document + parameters → rows → rendered output
    ├── Models/ReportTemplate.php   Stored document
    ├── Schema/                     Document format: validator, bands, block types
    ├── DataSources/                Registry, whitelist, query factory
    ├── Expressions/                Sandboxed {{ }} evaluator and formatters
    ├── Compiler/                   Document + rows → RenderedReport
    ├── Renderers/                  Markdown, HTML and PDF
    ├── Designer/                   Form state ⇄ document mapping, live preview
    ├── Filament/                   The template resource and its pages
    ├── Facades/ReportData.php
    ├── Rules/                      Validation rules
    ├── Support/Scope.php           Resolves owner and tenant
    └── Exceptions/
```

## Where validation happens

Validation lives in the **model**, not only in the form:

```php
static::saving(function (self $template): void {
    $template->syncSchemaWithColumns();

    app(ReportSchema::class)->validate($template->schema);
});
```

A document that saves cleanly but is malformed will fail later, at render time, where the
cause is much harder to trace. Putting the check at the storage boundary means no path —
panel, seeder, import, console command — can get a bad document into the table.

The designer also validates before saving, so people see what is wrong instead of an
exception page. Its pages build the document with `DocumentFormMapper`, then run the same
`ReportSchema` validator plus `ReportQueryFactory::validateBindings()`, which checks every
column, sort, group and filter against the source's whitelist without needing parameter
values. Field-level problems are marked on the field; whole-design problems stop the save with
a notification.

## The designer never shapes storage

Filament's builder keeps blocks as `{type, data}` items keyed by UUID, and a list filter value
is typed as comma-separated text. None of that reaches the database: `DocumentFormMapper`
translates in both directions, and a document round-trips through the designer unchanged.
The live preview renders from the same mapped document, capped at `preview.max_rows`.

### Errors are keyed by path

`InvalidReportSchema` carries errors keyed by where they occurred:

```php
[
    'bands.detail.0.columns' => ['The columns field is required.'],
    'bands.sidebar'          => ['Unknown band "sidebar". Expected one of: ...'],
]
```

Blocks are polymorphic — each `type` has its own shape — so they are validated separately
from the envelope rather than being forced into one flat rule set. All failures are
collected before throwing, so a document with four mistakes reports four, not one.

## Identity: columns and document

`key` and `title` exist both as database columns and inside the document. The columns are
authoritative — they are indexed, listed and searched without unpacking JSON — and are
written back into the document on every save:

```php
$schema['key'] = $this->key;
$schema['title'] = $this->title;
```

So the two can never drift apart, and a document exported to a file is still self-contained.

## Ownership and tenancy are opt-in

Both are off by default, but their columns always exist:

```php
$table->nullableMorphs('owner');
$table->nullableMorphs('tenant');
```

Polymorphic columns rather than foreign keys, because a distributable plugin cannot assume
the host application's table names or that a tenant model exists at all. Shipping the
columns unconditionally means a buyer who turns tenancy on later needs a data backfill, not
a migration against live customer data.

`Scope` resolves both, and `ReportTemplate::scopeVisible()` applies them to every query the
designer makes.

## Security boundaries

| Threat | Handling |
|---|---|
| Path traversal via template files | Paths are resolved with `realpath()` and refused unless inside the configured directory |
| Malformed documents reaching storage | Validated at the model's `saving` hook, not only in the form |
| Duplicate keys within a tenant | Unique index, plus `UniqueTemplateKey` for the untenanted case SQL cannot constrain |
| Cross-tenant leakage | `scopeVisible()` on every designer query |
| Code execution via expressions | `{{ ... }}` goes through a sandboxed evaluator that accepts only field and parameter references and allow-listed aggregates and formatters — never Blade or `eval` |
| Unexposed data via the designer or preview | Field pickers offer only whitelisted fields; saving and the preview both enforce the whitelist and scopes |
| Script injection in rendered output | Markdown and HTML renderers escape every value |
| Parameter values reaching SQL | Substituted only where a filter value is exactly one `{{ params.x }}` reference to a declared parameter, and always passed as a binding |

## Database portability

The `schema` column uses Laravel's `json` type, which maps to a native JSON column on MySQL
and PostgreSQL and to `TEXT` on SQLite. No vendor-specific JSON operators are used in
queries, so the same code runs on all three.
