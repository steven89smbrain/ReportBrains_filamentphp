# ReportBrains Report Designer — Documentation

A Filament plugin for designing reports. Templates are stored as JSON documents and
are rendered to Markdown, HTML, PDF or spreadsheets.

> **Status: in development.** Reports can be written, bound to data and rendered to
> Markdown or HTML today. The visual editor and PDF output do not exist yet — see
> [What works today](#what-works-today).
> This documentation only describes behaviour that is implemented and covered by tests.

## Contents

| Guide | What it covers |
|---|---|
| [01 — Installation](01-installation.md) | Getting the plugin into a Laravel + Filament application |
| [02 — Quick start](02-quick-start.md) | Creating and loading your first template |
| [03 — Document reference](03-document-reference.md) | Every key in the template JSON format |
| [04 — Configuration](04-configuration.md) | Ownership, tenancy, table name, file templates |
| [05 — Loading templates from code](05-loading-templates.md) | The repository API |
| [06 — Architecture](06-architecture.md) | How the pieces fit together, and why |
| [07 — Changelog](07-changelog.md) | What shipped in each milestone |
| [08 — Data sources](08-data-sources.md) | Exposing data to reports, safely |
| [09 — Expressions and rendering](09-expressions-and-rendering.md) | The `{{ }}` language, formatting, and the Markdown/HTML renderers |

## What works today

| Capability | Status |
|---|---|
| Store report documents in the database | ✅ Working |
| Validate documents against a versioned schema | ✅ Working |
| Manage templates in the Filament panel (JSON editor) | ✅ Working |
| Load templates from JSON files on disk | ✅ Working |
| Optional per-owner and per-tenant isolation | ✅ Working |
| Drag-and-drop visual editor | ⏳ Planned (M4) |
| Register data sources with a field whitelist | ✅ Working |
| Scopes, row caps and parameter validation | ✅ Working |
| Markdown and HTML rendering | ✅ Working |
| Sandboxed `{{ }}` expressions and aggregates | ✅ Working |
| Grouping with subtotals and grand totals | ✅ Working |
| PDF rendering | ⏳ Planned (M5) |

Planning documents for the unbuilt milestones live in `Planning/`.

## Requirements

- PHP 8.3+
- Laravel 13
- Filament 5
- MySQL, PostgreSQL or SQLite

## Licence

Commercial. One licence per project. See `packages/filament-report-designer/LICENSE.md`.
