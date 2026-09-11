<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use ReportBrains\ReportDesigner\DataSources\Contracts\DataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;

/**
 * A data source backed by an Eloquent model.
 *
 * Fields are declared one by one rather than read from the table, so adding a
 * column to a table never silently exposes it to every stored report.
 */
class EloquentSource implements DataSource
{
    /** @var array<string, Field> */
    private array $fields = [];

    /** @var array<string, Parameter> */
    private array $parameters = [];

    /** @var array<int, Closure> */
    private array $scopes = [];

    private ?string $label = null;

    private int $maxRows = 10000;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private readonly string $key,
        private readonly string $model,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label ?? $this->key;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Expose a column to reports.
     *
     * The label is required: the designer is used by people who should not have
     * to read raw column names.
     */
    public function addField(string $name, string $label, FieldType $type = FieldType::String, ?string $format = null): static
    {
        $this->fields[$name] = new Field($name, $label, $type, $format);

        return $this;
    }

    public function addParameter(string $name, string $label, FieldType $type = FieldType::String, bool $required = false, mixed $default = null): static
    {
        $this->parameters[$name] = new Parameter($name, $label, $type, $required, $default);

        return $this;
    }

    /**
     * Constrain every query this source makes.
     *
     * Scopes are applied on each fetch and cannot be removed by a report, which
     * is what keeps tenant and permission boundaries intact.
     *
     * @param  Closure(Builder): mixed  $scope
     */
    public function scope(Closure $scope): static
    {
        $this->scopes[] = $scope;

        return $this;
    }

    /**
     * Cap how many rows a single report may pull.
     */
    public function maxRows(int $maxRows): static
    {
        $this->maxRows = $maxRows;

        return $this;
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $name): ?Field
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * @return array<string, Parameter>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function rows(ReportQuery $query): iterable
    {
        $builder = $this->model::query();

        foreach ($this->scopes as $scope) {
            $scope($builder);
        }

        $this->applyFilters($builder, $query);
        $this->applySorting($builder, $query);

        // Relation fields are read by eager loading and resolved in PHP rather
        // than joined. Joins would need the report to name tables, which is
        // exactly what the whitelist exists to prevent.
        $builder->with($this->relationsFor($query));

        $limit = min($query->limit ?? $this->maxRows, $this->maxRows);

        return $builder->limit($limit)->get()
            ->map(fn (Model $record): array => $this->extractRow($record, $query))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function relationsFor(ReportQuery $query): array
    {
        $relations = [];

        foreach ($query->requiredFields() as $name) {
            $path = $this->fields[$name]?->relationPath();

            if ($path !== null) {
                $relations[$path] = true;
            }
        }

        return array_keys($relations);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRow(Model $record, ReportQuery $query): array
    {
        $row = [];

        foreach ($query->requiredFields() as $name) {
            $row[$name] = data_get($record, $name);
        }

        return $row;
    }

    private function applyFilters(Builder $builder, ReportQuery $query): void
    {
        foreach ($query->filters as $filter) {
            $field = $this->fields[$filter['field']] ?? null;

            if ($field === null) {
                throw UnknownField::onSource($filter['field'], $this->key);
            }

            // Filtering across a relation would need a whereHas against a
            // relation the report named; out of scope until it is needed.
            if ($field->isRelation()) {
                continue;
            }

            $this->applyFilter($builder, $field, $filter['operator'], $filter['value']);
        }
    }

    private function applyFilter(Builder $builder, Field $field, string $operator, mixed $value): void
    {
        // Values are always passed as bindings; the column name only ever comes
        // from the whitelist, never from the document.
        match ($operator) {
            '=', '!=', '<', '<=', '>', '>=' => $builder->where($field->name, $operator, $value),
            'contains' => $builder->where($field->name, 'like', '%'.$this->escapeLike((string) $value).'%'),
            'starts_with' => $builder->where($field->name, 'like', $this->escapeLike((string) $value).'%'),
            'ends_with' => $builder->where($field->name, 'like', '%'.$this->escapeLike((string) $value)),
            'in' => $builder->whereIn($field->name, (array) $value),
            'not_in' => $builder->whereNotIn($field->name, (array) $value),
            'between' => $builder->whereBetween($field->name, array_slice((array) $value, 0, 2)),
            default => throw UnknownField::operator($operator, $field->name),
        };
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    private function applySorting(Builder $builder, ReportQuery $query): void
    {
        foreach ($query->sort as $sort) {
            $field = $this->fields[$sort['field']] ?? null;

            if ($field === null) {
                throw UnknownField::onSource($sort['field'], $this->key);
            }

            // Sorting by a relation field would require a join; reports that
            // need it should group instead. Documented as a v1 limitation.
            if ($field->isRelation()) {
                continue;
            }

            $builder->orderBy($field->name, $sort['direction']);
        }
    }
}
