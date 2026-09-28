# 10 — The visual designer

Templates are designed in the panel at **Report Templates**. Nobody using the designer needs
to know the JSON format, the placeholder syntax or the names of database columns.

The page has two halves: the designer on the left and a **live preview** on the right, which
runs the report against real data as you work. On narrow screens the preview sits below.

## Report details

| Field | Notes |
|---|---|
| Title | Shown in listings and as the report heading in HTML output |
| Key | Lowercase, hyphen-separated. How code calls the report. Cannot be changed after creation |
| Description | Optional |

## The Data tab

Start here — the other tabs offer fields from the source chosen here.

| Setting | What it does |
|---|---|
| **Data source** | Which registered data the report reads. Only sources a developer registered are listed |
| **Group rows by** | Splits rows into groups, each with its own header, detail and footer |
| **Sort by** | One or more fields, each ascending or descending. Drag to change priority |
| **Filters** | Conditions rows must meet. The operators offered depend on the field's type. Each filter compares with a fixed value or a report parameter |

Field lists show the **labels** the developer gave each field, never column names.

For the "is one of", "is not one of" and "between" operators, type the values separated by
commas: `North, South`.

### Filters driven by parameters

When the source declares parameters, each filter offers **Compare with**:

- **A fixed value** — typed into the filter and stored with the template.
- **A report parameter** — chosen from the parameters the developer declared, such as
  *From date*. For **between**, pick a **From** and a **To** parameter; either may be left
  empty for an open-ended range.

A filter compared with a parameter is skipped when that parameter is left empty, so a single
template can serve "all branches" and "one branch". Dates compared with date-and-time fields
cover the whole day.

> Sorting and filtering are offered on a source's own fields only. Fields reached through a
> relation can be shown in tables and used for grouping.

## The Layout tab

A report is made of **bands**. Each band is a list of blocks you add, reorder by dragging,
collapse and remove.

| Band | Rendered |
|---|---|
| Document header | Once, at the top |
| Page header | Every page — PDF only |
| Group header | Each time the grouping value changes |
| Detail | The body — see below |
| Group footer | End of each group — subtotals |
| Page footer | Every page — PDF only |
| Document footer | Once, at the end — grand totals |

Leave a band empty to leave it out.

### Blocks

| Block | Settings |
|---|---|
| **Heading** | Text, level 1–6, alignment |
| **Text** | Text, alignment, bold, italic |
| **Table** | Columns — each a field, label, alignment and format. Drag columns to reorder |
| **Divider** | None |
| **Spacer** | Height in pixels |

When you pick a column's field, its label is filled in from the field's name; change it if you
like.

In the **Detail** band, a table lists every row. Any other block there is repeated once per
row.

### Inserting values into text

Headings and text have an **Insert field** button. It asks three questions:

1. **Field** — which value
2. **Show** — the value on each row, or a total, average, count, lowest or highest
3. **Format** — currency, number, date and so on

It then adds the right placeholder to the text, for example `{{ sum(total) | currency }}`.
Totals are calculated over the rows in the band: a subtotal in a group footer, a grand total
in the document footer.

## The Page tab

Paper size, orientation and margins, used for PDF output. The **Page header** and **Page
footer** bands on the Layout tab print on every page; use **Insert field → Page number** or
**Total pages** in them. See [PDF output](11-pdf-output.md).

The live preview shows the report body only — page headers and footers appear in the PDF.

## The preview

The preview re-renders as you edit, against live data from the chosen source.

- It reads at most **25 rows** by default (`preview.max_rows` in the config) and says so when
  it hits the limit.
- When the source has parameters, **Try the report with** shows one input per parameter above
  the preview. Change a value and the preview re-runs with it. These values are never saved
  with the template; left empty, a parameter uses its declared default, and a report that
  requires a parameter still previews.
- While a design is incomplete, the preview explains what is missing instead of failing —
  for example `Choose a data source to see a preview.`
- The preview enforces the same data boundaries as a real run: scopes apply, and fields a
  source does not expose cannot appear.

## Exporting

Once saved, a template's edit page has an **Export** action: pick **PDF**, **HTML** or
**Markdown**, fill in any parameters, and download. Export runs the saved template, so save
first to include recent changes.

## Saving

Saving validates the whole design. Problems on a specific field are marked on that field. A
problem with the design as a whole — such as a column a developer has since stopped
exposing — stops the save with a notification explaining why. Nothing invalid is ever stored.

## For developers: JSON

Two extras sit alongside the visual tools:

- **JSON tab** — the document the current design produces, read-only and live.
- **Import JSON** — a page action that replaces the design with a pasted document. Nothing is
  saved until you save the template, so you can review the preview first.

A panel used only by people who design visually can hide both:

```php
$panel->plugin(
    ReportDesignerPlugin::make()->jsonEditor(false),
);
```

## Where parameters come from

Parameters are declared by developers on the data source — see
[Using parameters in filters](08-data-sources.md#using-parameters-in-filters). The designer
lists them; it does not create them. That keeps what a report can ask for inside the same
boundary as what it can read.
