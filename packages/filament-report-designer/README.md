# Report Designer for Filament

Design reports visually inside your Filament panel, then export them as PDF, Excel, CSV, HTML or
Markdown — from the panel, from code, on the queue or from the command line.

- **Drag-and-drop designer** with a live preview against your real data
- **Data you control**: an explicit field whitelist, scopes that every read honours, and parameters
- **Report features**: grouping, subtotals, grand totals, date-range filters and page numbers
- **Output**: PDF that matches the preview, spreadsheets with real numbers and dates, HTML, Markdown

## Requirements

- PHP 8.3+
- Laravel 13
- Filament 5
- MySQL, PostgreSQL or SQLite
- For PDF: Google Chrome or Chromium on the server (recommended), or another
  [spatie/laravel-pdf](https://github.com/spatie/laravel-pdf) driver

## Installation

This is a commercial package. Your purchase comes with a licence key.

**1. Add the private repository**

```bash
composer config repositories.report-designer composer https://report-designer.composer.sh
```

**2. Save your credentials.** The username is the email address you purchased with; the password
is your licence key.

```bash
composer config --auth http-basic.report-designer.composer.sh you@example.com YOUR-LICENCE-KEY
```

This writes to `auth.json`. Keep that file out of version control.

**3. Install the package and a PDF driver, then migrate**

```bash
composer require reportbrains/filament-report-designer
composer require chrome-php/chrome
php artisan migrate
```

**4. Register the plugin** on the panel that should show the designer:

```php
use ReportBrains\ReportDesigner\ReportDesignerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ReportDesignerPlugin::make());
}
```

**5. Expose some data** in a service provider. Only fields you list can ever reach a report:

```php
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\Facades\ReportData;

ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
    $source
        ->setLabel('Orders')
        ->addField('invoice_no', 'Invoice number')
        ->addField('total', 'Total', FieldType::Currency)
        ->addField('customer.name', 'Customer')
        ->addParameter('from', 'From date', FieldType::Date)
        ->scope(fn ($query) => $query->whereBelongsTo(auth()->user()->team));
});
```

Open **Report Templates** in your panel and start designing.

## Running reports from code

```php
use ReportBrains\ReportDesigner\Facades\Report;

Report::template('monthly-sales')->with(['from' => '2026-09-01'])->toPdf();
Report::template('monthly-sales')->save('reports/september.xlsx', disk: 's3');
Report::template('monthly-sales')->queue('reports/2026.pdf');
```

```bash
php artisan report:render monthly-sales --output=reports/september.pdf --param=from=2026-09-01
```

## Documentation

The full guides ship with this package in [`docs/`](docs/README.md) — installation, the visual
designer, data sources, expressions, PDF output and running reports from code. They are also
published online; the address is on the product page.

## Licence

Commercial. One licence covers one project, including its staging and development copies. See
[LICENSE.md](LICENSE.md).
