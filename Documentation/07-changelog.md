# 07 — Changelog

Milestones as they complete. Roadmap for the unbuilt ones is in `Planning/04-roadmap.md`.

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
