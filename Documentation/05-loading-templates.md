# 05 — Loading templates from code

`TemplateRepository` is the way to read templates. Resolve it from the container:

```php
use ReportBrains\ReportDesigner\TemplateRepository;

$repository = app(TemplateRepository::class);
```

It is registered as a singleton, so it can also be constructor-injected.

## From the database

### `find(string $key): ReportTemplate`

Returns the stored template, honouring ownership and tenancy. Throws `TemplateNotFound`
when there is no match.

```php
$template = $repository->find('monthly-sales');

$template->title;          // "Monthly Sales"
$template->key;            // "monthly-sales"
$template->schema;         // the document, as an array
$template->schema_version; // 1
```

### `findOrNull(string $key): ?ReportTemplate`

The same lookup, returning `null` instead of throwing.

```php
if ($template = $repository->findOrNull('monthly-sales')) {
    // ...
}
```

## From files

### `fromFileKey(string $key): array`

Reads `{file_templates.path}/{key}.json` and returns the validated document.

```php
$document = $repository->fromFileKey('monthly-sales');
```

### `fromFile(string $path): array`

The same, for an explicit path. The path must resolve inside the configured template
directory — see [Configuration](04-configuration.md#file_templatespath).

```php
$document = $repository->fromFile(resource_path('reports/monthly-sales.json'));
```

Both methods validate the document on every read, so a file edited by hand cannot smuggle
in a malformed document.

## Exceptions

| Exception | Raised when |
|---|---|
| `TemplateNotFound` | No template for that key, no file at that path, or the path is outside the template directory |
| `InvalidReportSchema` | The document failed validation. Carries `errors` keyed by the failing path, plus `messages()` and `summary()` helpers |

```php
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;

try {
    $document = $repository->fromFileKey('monthly-sales');
} catch (InvalidReportSchema $exception) {
    report($exception->summary());          // one readable line
    logger()->debug($exception->errors);    // keyed by path, e.g. "bands.detail.0.columns"
}
```

## Writing templates from code

Create templates through the model. The document is validated on save, so an invalid one
throws rather than being stored:

```php
use ReportBrains\ReportDesigner\Models\ReportTemplate;

ReportTemplate::create([
    'key' => 'monthly-sales',
    'title' => 'Monthly Sales',
    'schema' => [
        'schema_version' => 1,
        'data' => ['source' => 'sales'],
        'bands' => [
            'detail' => [
                ['type' => 'table', 'columns' => [['field' => 'total']]],
            ],
        ],
    ],
]);
```

`key` and `title` are copied into the stored document automatically, and `schema_version`
defaults to the current version when omitted.

## Validating without saving

```php
use ReportBrains\ReportDesigner\Schema\ReportSchema;

$schema = app(ReportSchema::class);

$schema->isValid($document);  // bool
$schema->validate($document); // returns the document, or throws InvalidReportSchema
```
