<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

use Closure;
use Illuminate\Database\Eloquent\Model;
use ReportBrains\ReportDesigner\DataSources\Contracts\DataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;

/**
 * The set of data a report is allowed to read.
 *
 * Sources are registered in application code — never from the panel — so the
 * data a stored template can reach is fixed by the developer, not by whoever
 * can edit templates.
 */
class DataSourceRegistry
{
    /** @var array<string, DataSource> */
    private array $sources = [];

    /**
     * Register an Eloquent-backed source.
     *
     * @param  class-string<Model>  $model
     * @param  Closure(EloquentSource): mixed  $definition
     */
    public function eloquent(string $key, string $model, Closure $definition): EloquentSource
    {
        $source = new EloquentSource($key, $model);

        $definition($source);

        $this->register($source);

        return $source;
    }

    public function register(DataSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    /**
     * @throws UnknownDataSource
     */
    public function get(string $key): DataSource
    {
        return $this->sources[$key] ?? throw UnknownDataSource::key($key, $this->keys());
    }

    /**
     * @return array<string, DataSource>
     */
    public function all(): array
    {
        return $this->sources;
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->sources);
    }

    /**
     * Sources as label-keyed options for the designer's source picker.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return array_map(fn (DataSource $source): string => $source->label(), $this->sources);
    }

    public function flush(): void
    {
        $this->sources = [];
    }
}
