# 03 — Document reference

The report document is the single source of truth for a report. It is stored as JSON, and
every renderer reads from it. This page documents **schema version 1**.

Anything that reaches storage is validated first, whether it came from the panel, a seeder,
a console command or a file — a malformed document that saves cleanly would otherwise fail
much later, at render time, where the cause is far harder to trace.

## Top level

| Key | Type | Required | Description |
|---|---|---|---|
| `schema_version` | integer | ✅ | Must be `1`. Defaulted on save if omitted |
| `key` | string | ✅ | Lowercase slug, e.g. `monthly-sales`. Mirrored from the database column |
| `title` | string | ✅ | Human-readable name. Mirrored from the database column |
| `params` | array | — | Runtime inputs — see [Parameters](#parameters) |
| `data` | object | ✅ | Where rows come from — see [Data](#data) |
| `page` | object | — | Paper setup for paginated output — see [Page](#page) |
| `bands` | object | ✅ | The report body — see [Bands](#bands) |

`key` and `title` are written back from the database columns on every save, so the stored
document and its columns can never drift apart.

## Parameters

Inputs the report asks for at run time. Without these, a template has to be duplicated
just to change a date range.

```json
"params": [
    { "name": "from", "type": "date", "label": "From date", "required": true },
    { "name": "branch", "type": "select", "source": "branches", "label": "Branch" }
]
```

| Key | Type | Required | Notes |
|---|---|---|---|
| `name` | string | ✅ | Lowercase, underscores allowed. Referenced as `{{ params.name }}` |
| `type` | string | ✅ | `string`, `number`, `date`, `boolean` or `select` |
| `label` | string | — | Shown on the parameter form |
| `required` | boolean | — | |
| `source` | string | — | For `select`: where the options come from |

## Data

```json
"data": {
    "source": "sales",
    "filters": [],
    "sort": [{ "field": "created_at", "dir": "desc" }],
    "group_by": ["branch.name"]
}
```

| Key | Type | Required | Notes |
|---|---|---|---|
| `source` | string | ✅ | Name of a registered data source |
| `filters` | array | — | Each entry is `{field, operator, value}`. The operator must be allowed for the field's type |
| `sort` | array | — | Each entry needs `field`; `dir` is `asc` or `desc` |
| `group_by` | array of strings | — | Drives the `group_header` and `group_footer` bands |

`source` must name a registered data source — see
[Data sources](08-data-sources.md). The Filament form rejects an unknown source when saving,
and `ReportQueryFactory` throws `UnknownDataSource` at run time.

`sort`, `group_by` and every `columns[].field` must name a field the source exposes.
Anything else is refused rather than silently dropped.

## Page

Only used by paginated renderers such as PDF. Ignored by Markdown and HTML.

```json
"page": {
    "size": "A4",
    "orientation": "portrait",
    "margin": { "top": 20, "right": 15, "bottom": 20, "left": 15 }
}
```

| Key | Allowed values |
|---|---|
| `size` | `A3`, `A4`, `A5`, `Letter`, `Legal` |
| `orientation` | `portrait`, `landscape` |
| `margin.*` | Any non-negative number, in millimetres |

## Bands

Each key is a band name; each value is an ordered array of blocks.

| Band | When it renders |
|---|---|
| `document_header` | Once, at the start |
| `page_header` | Every page — paginated output only |
| `group_header` | Each time a `group_by` value changes |
| `detail` | **Once per row** |
| `group_footer` | End of each group |
| `page_footer` | Every page — paginated output only |
| `document_footer` | Once, at the end |

Unknown band names are rejected, and the error names the band that failed.

## Blocks

Every block has a `type`. Unknown types are rejected.

### `heading`

```json
{ "type": "heading", "level": 2, "content": "Branch: {{ group.value }}" }
```

| Key | Type | Required | Notes |
|---|---|---|---|
| `level` | integer | ✅ | 1–6 |
| `content` | string | ✅ | |
| `align` | string | — | `left`, `center`, `right` |

### `text`

```json
{ "type": "text", "content": "Subtotal: {{ sum(total) }}", "align": "right", "bold": true }
```

| Key | Type | Required |
|---|---|---|
| `content` | string | ✅ |
| `align` | string | — |
| `bold` | boolean | — |
| `italic` | boolean | — |

### `table`

```json
{
    "type": "table",
    "columns": [
        { "field": "invoice_no", "label": "Invoice", "width": "20%" },
        { "field": "total", "label": "Total", "align": "right", "format": "currency" }
    ]
}
```

| Key | Type | Required | Notes |
|---|---|---|---|
| `columns` | array | ✅ | At least one |
| `columns[].field` | string | ✅ | Dot notation for relations, e.g. `customer.name` |
| `columns[].label` | string | — | Defaults to the field name |
| `columns[].align` | string | — | `left`, `center`, `right` |
| `columns[].width` | string | — | e.g. `20%` |
| `columns[].format` | string | — | Formatter name. Formatters arrive in M3 |

### `divider`

```json
{ "type": "divider" }
```

Takes no other keys.

### `spacer`

```json
{ "type": "spacer", "height": 24 }
```

| Key | Type | Notes |
|---|---|---|
| `height` | integer | 0–500. Optional |

## Expressions

`{{ ... }}` placeholders are evaluated by a deliberately tiny, sandboxed language — see
[Expressions and rendering](09-expressions-and-rendering.md).

| Expression | Meaning |
|---|---|
| `{{ params.from }}` | A report parameter |
| `{{ total }}` | A field on the current row |
| `{{ customer.name }}` | A field across a relation |
| `{{ group.value }}` | The current group's value |
| `{{ sum(total) }}` | An aggregate over the rows in scope |
| `{{ sum(total) \| currency }}` | Any of the above, formatted |

There is no arithmetic, no nesting and no method calls. Anything else is refused.

## Versioning

`schema_version` exists so that documents written today can be migrated when the format
changes. Adding a block type or a band is a schema change: the version must be bumped and
an upgrade path provided for documents written against the previous version.
