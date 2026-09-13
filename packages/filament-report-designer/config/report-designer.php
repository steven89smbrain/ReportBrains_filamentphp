<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database table
    |--------------------------------------------------------------------------
    |
    | Rename this if "report_templates" collides with a table in the host
    | application. Change it before running the migration.
    |
    */

    'table_name' => 'report_templates',

    /*
    |--------------------------------------------------------------------------
    | Ownership
    |--------------------------------------------------------------------------
    |
    | When enabled, each template records the model that created it, and the
    | designer only lists templates owned by the current user. Leave disabled
    | to let everyone with panel access share every template.
    |
    */

    'ownership' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | When enabled, templates are isolated per tenant. The tenant is resolved
    | from Filament's current tenant by default; supply a closure to resolve it
    | some other way. The columns exist either way, so enabling this later does
    | not require a schema change — only a data backfill.
    |
    */

    'tenancy' => [
        'enabled' => false,
        'resolver' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Designer preview
    |--------------------------------------------------------------------------
    |
    | The live preview beside the designer runs the report against real data.
    | It is capped so that editing a template never pulls a whole table.
    |
    */

    'preview' => [
        'max_rows' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    |
    | How values are rendered by the "currency", "number", "date" and related
    | formatters. These are plain PHP settings rather than ext-intl locales, so
    | output does not change with the host's PHP build.
    |
    | For Indonesian formatting: symbol "Rp ", "," decimal, "." thousands,
    | date "d/m/Y".
    |
    */

    'formatting' => [
        'decimals' => 2,
        'decimal_separator' => '.',
        'thousands_separator' => ',',

        'currency_symbol' => '$',
        'currency_symbol_after' => false,
        'currency_decimals' => 2,

        'percent_suffix' => '%',

        'date_format' => 'Y-m-d',
        'datetime_format' => 'Y-m-d H:i',
    ],

    /*
    |--------------------------------------------------------------------------
    | File templates
    |--------------------------------------------------------------------------
    |
    | Templates may also be shipped as JSON files and committed to version
    | control. Files are read only. Paths outside this directory are rejected.
    |
    */

    'file_templates' => [
        'path' => resource_path('reports'),
    ],

];
