# 07 — Changelog

Milestones as they complete. Roadmap for the unbuilt ones is in `Planning/04-roadmap.md`.

---

## M4 — Visual designer · 13 Sep 2026

Reports can now be designed in the panel without writing JSON.

**Designer**
- Tabbed designer — **Layout**, **Data**, **Page** and **JSON** — beside a live preview
- One drag-and-drop builder per band, with heading, text, table, divider and spacer blocks
- Table columns picked from the source's fields by label; the column label fills in from the
  field
- **Insert field** action on headings and text: pick a field, a total or average, and a format,
  and the placeholder is written for you
- Data tab: source, grouping, multi-level sorting and filters whose operators follow the field
  type
- Page tab: paper size, orientation and margins

**Preview**
- `ReportPreview` renders the design against live data on every change, capped at
  `preview.max_rows` (default 25)
- Parameters fall back to their defaults, so reports with required parameters still preview
- Incomplete designs show what is missing instead of an error page; data boundaries still apply

**Saving**
- `DocumentFormMapper` translates between the designer's form state and the stored document,
  so the form's shape never leaks into storage; a document round-trips unchanged
- Saving validates the schema and every binding; whole-design problems stop the save with a
  notification. `ReportQueryFactory::validateBindings()` makes that check possible before any
  parameters exist
- Stored `params` are preserved when a design is saved

**Developers**
- Read-only JSON tab and an **Import JSON** action; `ReportDesignerPlugin::jsonEditor(false)`
  hides both

**Removed**
- The M0 placeholder "Reports" page, now superseded by the designer

**Tests** — 26 added (148 total), including the mapper round trip, preview boundaries and designer
create, edit and import flows.

### Not included

Filters are built with the designer's own whitelist-bound controls rather than Filament's
query builder, which applies conditions directly to an Eloquent query and would bypass the
source whitelist. Report parameters cannot yet be edited in the designer, and do not yet
filter data.

---

## M3 — Compiler and renderers · 11 Sep 2026

Reports now produce output. A template stored in the panel can be bound to data, compiled
and written out as Markdown or HTML.

**Expression evaluation**
- `ExpressionEvaluator` understands exactly three forms — a field, a `params.`/`group.`
  reference, and an aggregate — each optionally piped into one formatter
- No arithmetic, no nesting, no method calls, no access to the container, facades or the
  request. Documents are user input, so Blade and `eval` were never options
- Aggregates `sum`, `avg`, `count`, `min`, `max`, scoped to the current band's rows
- Unknown functions, formatters and references are refused rather than rendered empty

**Formatting**
- `currency`, `number`, `integer`, `percent`, `date`, `datetime`, `upper`, `lower`
- Driven by config rather than `ext-intl`, which is not guaranteed on a buyer's server

**Compiler**
- Repeats the detail band per row, except for `table` blocks, which consume the whole row
  set — a table is already a repeating structure
- Splits rows by `group_by`, emitting header, detail and footer bands per group, with
  aggregates scoped to that group
- Refuses more than one grouping level rather than silently dropping the extras
- Produces a `RenderedReport` that is pure data: no expressions, no field references, no
  database access

**Renderers**
- `MarkdownRenderer` — tables with column alignment; escapes data, including the pipe, so a
  value cannot split a column. Page setup is skipped, since Markdown has no pages
- `HtmlRenderer` — full document or embeddable fragment, band-scoped CSS classes, and every
  value escaped so report data cannot become markup

**Tests** — 52 added, including an end-to-end pass from a stored template through a scoped
data source to grouped Markdown, plus XSS and Markdown-injection cases.

### Not included

PDF output (M5) and the visual editor (M4). Relation fields still cannot be sorted or
filtered on.

---

## M2 — Data sources · 11 Sep 2026

Reports can now be bound to data, through a whitelist the developer controls.

**Registry**
- `ReportData` facade and `DataSourceRegistry`. Sources are registered in application code
  and never from the panel
- `DataSource` interface speaks in rows rather than query builders, so a source backed by a
  view, stored procedure or remote API can implement it later without a redesign
- `EloquentSource` is the implementation shipped today

**The whitelist**
- Fields are declared one at a time with a **required** human label, so adding a column to a
  table never silently exposes it, and the designer never has to show `customer_id`
- Relation fields via dot notation, eager-loaded and resolved in PHP rather than joined
- Filter operators are constrained by field type — a date field cannot use `contains`
- `scope()` applies on every read and cannot be removed by a report
- `maxRows()` caps a report's rows; a query cannot raise the cap

**Binding a document to data**
- `ReportQueryFactory` turns a document plus runtime parameters into a validated
  `ReportQuery`, checking every column, sort, group and filter against the whitelist
- Parameters are validated against their declared types; undeclared ones are dropped rather
  than passed through
- `DocumentFields` collects the fields a document's blocks actually refer to

**Authoring**
- The Filament form rejects a document whose `data.source` is not registered, naming the
  sources that are

**Tests** — 32 added, including the security boundaries: an unexposed column stays
unreachable, a scope survives a template filtering the same column, and a query cannot lift
the row cap.

### Not included

`{{ ... }}` expressions are still stored verbatim and not evaluated, and nothing renders
yet. Both arrive in M3. Relation fields can be displayed and grouped but not sorted or
filtered on.

---

## M1 — Template storage and validation · 11 Sep 2026

Report documents can now be written, validated and stored.

**Document format (schema version 1)**
- `ReportSchema` validates the whole document: identity, parameters, data block, page setup
  and bands
- `BandName` enum — seven bands, with `isRepeating()` and `isPaginatedOnly()`
- `BlockType` enum — `heading`, `text`, `table`, `divider`, `spacer`, each carrying its own
  validation rules
- Errors are keyed by path (`bands.detail.0.columns`) and all failures are collected before
  throwing, rather than stopping at the first

**Storage**
- `report_templates` migration; table name configurable before migrating
- `ReportTemplate` model validating at the `saving` hook, so no code path can store a
  malformed document
- `key` and `title` mirrored between columns and document on every save
- Opt-in ownership and tenancy via always-present polymorphic columns

**Loading**
- `TemplateRepository` reads from the database (`find`, `findOrNull`) and from disk
  (`fromFile`, `fromFileKey`)
- File reads are confined to the configured directory; traversal is refused

**Panel**
- `ReportTemplateResource` with list, create and edit pages at `/admin/report-templates`
- JSON code editor with schema validation surfaced as form errors
- `key` is immutable after creation, since it is the identifier code calls

**Configuration**
- `config/report-designer.php`: `table_name`, `ownership`, `tenancy`, `file_templates.path`

**Tests** — 32 added, covering the validator, the model, the repository (including the
traversal guard) and the Filament resource.

**Licence** — package set to `proprietary` with a commercial licence file, one licence per
project.

### Not included

Data sources are not resolved and `{{ ... }}` expressions are not evaluated — `data.source`
accepts any string and expressions are stored verbatim. Those arrive in M2 and M3.

---

## M0 — Foundation · 11 Sep 2026

- Git repository initialised; remote set to `ReportBrains_filamentphp`
- Filament v5.7.8 installed, admin panel at `/admin`
- Package `reportbrains/filament-report-designer` created and linked through a Composer
  path repository, symlinked into `vendor/`
- `ReportDesignerPlugin` registered on the panel, discovering its own resources and pages
- `User` made to implement `FilamentUser` — without it Filament returns 403 outside `local`,
  so the panel would have failed in production

**Tests** — 4, covering plugin registration, page discovery, rendering and the
authentication boundary.
