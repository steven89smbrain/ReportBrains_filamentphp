# Changelog

All notable changes to `reportbrains/filament-report-designer` are documented here.
This project follows [Semantic Versioning](https://semver.org): breaking changes only arrive in a
new major version.

## 1.0.0 — 2026-09-14

First release.

### Designer

- Visual report designer in the Filament panel: drag-and-drop blocks — heading, text, table,
  divider, spacer — arranged in bands for the document, page, group and detail
- Live preview against real data while designing, with inputs to try report parameters
- Fields chosen by human-readable labels; **Insert field** writes totals, averages, counts and
  page numbers without typing any syntax
- Data tab for the source, grouping, sorting and filters; Page tab for paper size, orientation
  and margins
- JSON view and import for developers, which a panel can switch off

### Data

- Data sources registered in code with an explicit field whitelist, required labels, scopes that
  every read honours, parameters and row caps
- Filters compared with fixed values or report parameters, including date ranges that cover whole
  days; an empty optional parameter leaves its filter out
- Optional per-owner and per-tenant isolation of templates

### Output

- Markdown, HTML, PDF, CSV and Excel (XLSX)
- PDF through spatie/laravel-pdf with a driver of your choice; Chromium-based drivers match the
  designer preview. Page headers and footers with page numbers
- Spreadsheets export raw values with a group column, and are protected against formula injection
- **Export** action on every template

### Running reports

- `Report` facade: load a stored template, a file template or a document; set parameters; render,
  save to any disk, download or queue
- Queued renders run as the user who requested them, so data scopes still apply
- `report:render` Artisan command, schedulable, with parameters and `--as`
- Register custom output formats

### Documentation

- Full guides included in `docs/`: installation, quick start, the visual designer, data sources,
  expressions, PDF output, running reports from code, configuration and architecture

### Requirements

- PHP 8.3+, Laravel 13, Filament 5
- MySQL, PostgreSQL or SQLite
- For PDF: a spatie/laravel-pdf driver — `chrome-php/chrome` with Chrome or Chromium is recommended
