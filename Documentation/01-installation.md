# 01 — Installation

## Requirements

| | Minimum |
|---|---|
| PHP | 8.3 |
| Laravel | 13 |
| Filament | 5 |
| Database | MySQL, PostgreSQL or SQLite |

## Installing the package

> **Note:** the package is not published to a repository yet. Distribution is still an
> open decision — see `Planning/05-keputusan-terbuka.md`. Until then, install it from a
> local path as described under *Development setup* below.

Once distribution is set up, installation will be:

```bash
composer require reportbrains/filament-report-designer
```

## Installing a PDF driver

PDF output needs one PDF driver. The recommended one prints with Chrome:

```bash
composer require chrome-php/chrome
```

It also needs Google Chrome or Chromium installed on the server. Other drivers, and the
trade-offs between them, are covered in [PDF output](11-pdf-output.md#choosing-a-driver).
Markdown and HTML output need nothing extra.

## Running the migration

The package ships one migration, which creates the `report_templates` table. It is loaded
automatically, so no publishing step is needed:

```bash
php artisan migrate
```

If the table name collides with one of your own, publish the config and change
`table_name` **before** migrating:

```bash
php artisan vendor:publish --tag=report-designer-config
```

## Registering the plugin

Add the plugin to the panel that should show the report designer:

```php
use ReportBrains\ReportDesigner\ReportDesignerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(ReportDesignerPlugin::make());
}
```

The plugin registers its own resources, so nothing else needs listing.

For a panel whose users design reports visually and should never see the underlying JSON,
switch the developer tools off:

```php
->plugin(ReportDesignerPlugin::make()->jsonEditor(false));
```

## Panel access

Filament returns **403** outside the `local` environment unless your user model implements
`FilamentUser`. This is Filament's own safeguard, not something the plugin adds, but it will
stop you reaching the designer in staging or production if you skip it:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin; // your rule here
    }
}
```

## Verifying

```bash
php artisan route:list --path=report-templates
```

You should see index, create and edit routes. Open `/admin/report-templates` in the panel.

---

## Development setup

To work on the plugin itself, add it as a path repository in the host application's
`composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/filament-report-designer",
        "options": { "symlink": true }
    }
]
```

```bash
composer require reportbrains/filament-report-designer:@dev
```

Composer symlinks the package into `vendor/`, so edits take effect immediately with no
reinstall step.

Run the test suite from the host application:

```bash
php artisan test
```
