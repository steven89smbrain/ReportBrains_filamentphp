# 08 — Data sources

A report can only read data a developer has explicitly exposed. Sources are registered in
application code, never from the panel, so what a stored template can reach is fixed by you
— not by whoever can edit templates.

This matters because templates are user input. If a template could name any table or column,
anyone able to edit one could read the whole database.

## Registering a source

Register sources in a service provider's `boot()` method:

```php
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\Facades\ReportData;

public function boot(): void
{
    ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source
            ->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('total', 'Total', FieldType::Currency)
            ->addField('customer.name', 'Customer name')
            ->addParameter('from', 'From date', FieldType::Date, required: true)
            ->scope(fn (Builder $query) => $query->whereBelongsTo(auth()->user()->tenant))
            ->maxRows(5000);
    });
}
```

The first argument is the key a report document names in `data.source`.

## Fields

```php
->addField(string $name, string $label, FieldType $type = FieldType::String, ?string $format = null)
```

**The label is required.** The designer is used by people who should never have to read
`customer_id`, so there is no way to expose a field without naming it properly.

Fields are declared one at a time rather than read from the table. Adding a column to a
table therefore never silently exposes it to every stored report — which is exactly what you
want for `password_hash`.

### Relation fields

Use dot notation:

```php
->addField('customer.name', 'Customer name')
->addField('customer.city', 'City')
```

Relations are eager-loaded and resolved in PHP rather than joined, because a join would
require the report to name tables — the thing the whitelist exists to prevent.

> **Limitation in v1:** relation fields can be displayed and grouped, but not sorted or
> filtered on. Sorting and filtering apply to the source's own columns. A report that needs
> ordering by a related value should group by it instead.

## Field types

| Type | Allowed filter operators |
|---|---|
| `String` | `=` `!=` `contains` `starts_with` `ends_with` `in` `not_in` |
| `Number`, `Currency` | `=` `!=` `<` `<=` `>` `>=` `between` `in` `not_in` |
| `Date`, `DateTime` | `=` `!=` `<` `<=` `>` `>=` `between` |
| `Boolean` | `=` |

An operator outside its type's list is rejected, so a date field cannot be filtered with
`contains`.

## Parameters

```php
->addParameter(
    name: 'from',
    label: 'From date',
    type: FieldType::Date,
    required: true,
    default: null,
)
```

Parameters are validated before any query runs. A missing required parameter throws
`InvalidReportParameters` with the failures keyed by parameter name.

**Parameters the source did not declare are dropped**, not passed through, so a document
cannot smuggle extra values into a source.

Values are normalised after validation: a `Date` parameter typed as `15 September 2026`
becomes `2026-09-15`, a `Number` becomes a number, a `Boolean` becomes `true` or `false`.

### Using parameters in filters

A filter in a report document can take its value from a parameter the source declares:

```json
{ "field": "ordered_at", "operator": "between", "value": ["{{ params.from }}", "{{ params.to }}"] }
```

In the designer this is **Compare with → A report parameter**, so report designers never type
the reference themselves.

| Situation | What happens |
|---|---|
| The parameter has a value | It is used, always as a query binding |
| The parameter is empty | The filter is **left out** — an empty "status" means every status, not none |
| A range has only one bound | It becomes on-or-after, or on-or-before |
| A `Date` is compared with a `DateTime` field | It covers the **whole day**, so "to 30 September" includes orders placed at 18:00 that day |
| The reference names a parameter the source does not declare | `UnknownParameter`, both when saving and at run time |
| The value only *contains* a reference, e.g. `"North {{ params.x }}"` | Compared as literal text; nothing is substituted |

The whole-day rule is what most people expect and what naive date filtering gets wrong:
compared against a date-time column, `<= 2026-09-30` would otherwise mean "before midnight
on the 30th" and silently drop that day's rows.

Default values make a report useful before anyone picks dates:

```php
->addParameter('from', 'From date', FieldType::Date, default: now()->subDays(30)->toDateString())
->addParameter('to', 'To date', FieldType::Date, default: now()->toDateString())
```

## Scopes

```php
->scope(fn (Builder $query) => $query->whereBelongsTo(auth()->user()->tenant))
```

Scopes are applied on **every** read and cannot be removed by a report. Add as many as you
need; they all apply.

This is the mechanism that keeps tenant and permission boundaries intact. A template that
filters on the same column a scope constrains still cannot see past the scope:

```php
// Source scoped to the North branch.
// A template filtering branch = "South" gets nothing, not the South rows.
```

Any source carrying data that differs per user or per tenant **must** have a scope. Nothing
else enforces that boundary.

## Row caps

```php
->maxRows(5000)   // default: 10000
```

A hard ceiling on how many rows one report may pull. A query cannot raise it — passing a
larger limit still yields at most `maxRows`.

## Reading rows directly

Usually the compiler does this for you, but a source can be read directly:

```php
use ReportBrains\ReportDesigner\DataSources\ReportQuery;

$rows = ReportData::get('orders')->rows(new ReportQuery(
    fields: ['invoice_no', 'total'],
    filters: [['field' => 'total', 'operator' => '>', 'value' => 200]],
    sort: [['field' => 'total', 'direction' => 'desc']],
));
```

Each row is an array keyed by field name. Only the requested fields are present.

## Turning a document into a query

`ReportQueryFactory` is the boundary where a stored template stops being text and starts
touching data. Every field the document names is checked against the whitelist here:

```php
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;

$query = app(ReportQueryFactory::class)->make($document, ['from' => '2026-09-01']);
$rows = app(ReportData::class)->get($document['data']['source'])->rows($query);
```

It throws rather than silently dropping anything:

| Exception | Raised when |
|---|---|
| `UnknownDataSource` | `data.source` names a source that is not registered |
| `UnknownField` | A column, sort field, group field or filter field is not on the whitelist, or an operator is not allowed for the field's type |
| `UnknownParameter` | A filter refers to a parameter the source does not declare |
| `InvalidReportParameters` | A required parameter is missing or a supplied one has the wrong type |

## Validation while authoring

The Filament form checks `data.source` against the registry, so an unregistered source is
reported when the template is saved rather than when someone is waiting for a report:

```
The data source [payroll] is not registered. Available: orders, users.
```

## Sources other than Eloquent

`DataSource` is an interface, and it deliberately speaks in rows rather than query builders:

```php
interface DataSource
{
    public function key(): string;
    public function label(): string;
    public function fields(): array;
    public function field(string $name): ?Field;
    public function parameters(): array;
    public function rows(ReportQuery $query): iterable;
}
```

A source backed by a database view, a stored procedure or a remote API has no query builder
to hand back — returning rows is the one shape all of them can satisfy. Implement the
interface and register it:

```php
ReportData::register(new MyApiSource);
```

`EloquentSource` is the only implementation shipped today.
