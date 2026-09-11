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
