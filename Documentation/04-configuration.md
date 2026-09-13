# 04 — Configuration

Publish the config file to change any of these:

```bash
php artisan vendor:publish --tag=report-designer-config
```

That writes `config/report-designer.php`. All keys have working defaults, so publishing is
only necessary if you want to change something.

## `table_name`

```php
'table_name' => 'report_templates',
```

Change this if the default collides with a table in your application. **Set it before
running the migration** — the migration reads this value, so changing it afterwards leaves
the old table behind.

## `ownership`

```php
'ownership' => [
    'enabled' => false,
],
```

When enabled, each template records the user who created it, and the designer only lists
templates owned by the current user. When disabled (the default), everyone with panel
access shares every template.

The `owner_type` / `owner_id` columns exist either way, so switching this on later does not
need a migration — only a backfill of the existing rows.

## `tenancy`

```php
'tenancy' => [
    'enabled' => false,
    'resolver' => null,
],
```

When enabled, templates are isolated per tenant. The tenant comes from Filament's current
tenant by default. If your application resolves tenants some other way, supply a closure:

```php
'resolver' => fn () => auth()->user()?->currentTeam,
```

The closure must return an Eloquent model or `null`.

As with ownership, the `tenant_type` / `tenant_id` columns are always present, so enabling
tenancy later is a data change rather than a schema change.

> **Uniqueness caveat.** The table has a unique index on
> `(tenant_type, tenant_id, key)`. SQL treats `NULL`s as distinct, so that index does not
> constrain rows with no tenant — which is every row while tenancy is off. The
> `UniqueTemplateKey` validation rule covers that case in the application layer, which is
> why duplicate keys are still rejected with tenancy disabled.

## `file_templates.path`

```php
'file_templates' => [
    'path' => resource_path('reports'),
],
```

Where JSON file templates are read from. Files are read-only and are validated on every
read.

**Paths outside this directory are refused.** A path that resolves outside it — through
`..` or as an absolute path — throws `TemplateNotFound` rather than being read. This matters
because template paths can come from configuration or from a request, and without the guard
`../../.env` would resolve to a readable file.

## `preview.max_rows`

```php
'preview' => [
    'max_rows' => 25,
],
```

The most rows the designer's live preview reads. The preview runs against real data on every
change, so this keeps editing a template from pulling a whole table.

## `formatting`

Separators, currency symbol and date formats used by the `currency`, `number`, `date` and
related formatters. See [Expressions and rendering](09-expressions-and-rendering.md#formatting-configuration).

## Plugin options

Set on the plugin where it is registered, rather than in the config file:

```php
$panel->plugin(
    ReportDesignerPlugin::make()
        ->jsonEditor(false), // hide the JSON tab and Import JSON action
);
```
