<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use ReportBrains\ReportDesigner\DataSources\Contracts\DataSource;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;

/**
 * @method static EloquentSource eloquent(string $key, string $model, Closure $definition)
 * @method static void register(DataSource $source)
 * @method static bool has(string $key)
 * @method static DataSource get(string $key)
 * @method static array<string, DataSource> all()
 * @method static array<int, string> keys()
 * @method static array<string, string> options()
 * @method static void flush()
 *
 * @see DataSourceRegistry
 */
class ReportData extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataSourceRegistry::class;
    }
}
