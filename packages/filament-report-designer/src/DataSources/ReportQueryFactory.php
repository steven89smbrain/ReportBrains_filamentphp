<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use ReportBrains\ReportDesigner\DataSources\Contracts\DataSource;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Exceptions\UnknownParameter;
use ReportBrains\ReportDesigner\Schema\DocumentFields;

/**
 * Turns a report document plus runtime parameters into a validated ReportQuery.
 *
 * This is the boundary where a stored template stops being text and starts
 * touching data, so every field it names is checked against the source's
 * whitelist here, and every parameter a filter refers to must be one the
 * source declared. A source's `rows()` can then trust what it is handed.
 */
class ReportQueryFactory
{
    private const DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(
        private readonly DataSourceRegistry $registry,
        private readonly DocumentFields $documentFields,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     *
     * @throws UnknownDataSource
     * @throws UnknownField
     * @throws UnknownParameter
     * @throws InvalidReportParameters
     */
    public function make(array $document, array $parameters = []): ReportQuery
    {
        $source = $this->validateBindings($document);

        return $this->build($document, $source, $this->resolveParameters($source, $parameters));
    }

    /**
     * Check that everything a document names exists on its source, without
     * needing parameter values.
     *
     * Used when a template is saved, where no one has supplied parameters yet
     * but a column the source does not expose — or a filter referring to a
     * parameter it does not declare — is already a mistake.
     *
     * @param  array<string, mixed>  $document
     *
     * @throws UnknownDataSource
     * @throws UnknownField
     * @throws UnknownParameter
     */
    public function validateBindings(array $document): DataSource
    {
        $source = $this->sourceFor($document);

        $this->build($document, $source, []);

        return $source;
    }

    /**
     * A query for the designer preview: bindings are still enforced, but
     * parameters are optional — supplied values are used when given, declared
     * defaults otherwise — and the row count is capped.
     *
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     *
     * @throws UnknownDataSource
     * @throws UnknownField
     * @throws UnknownParameter
     * @throws InvalidReportParameters
     */
    public function preview(array $document, int $limit, array $parameters = []): ReportQuery
    {
        $source = $this->validateBindings($document);

        return $this->build($document, $source, $this->resolvePreviewParameters($source, $parameters))
            ->withLimit($limit);
    }

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws UnknownDataSource
     */
    public function sourceFor(array $document): DataSource
    {
        return $this->registry->get((string) ($document['data']['source'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     */
    private function build(array $document, DataSource $source, array $parameters): ReportQuery
    {
        $data = $document['data'] ?? [];

        return new ReportQuery(
            fields: $this->resolveFields($document, $source),
            filters: $this->resolveFilters($data['filters'] ?? [], $source, $parameters),
            sort: $this->resolveSort($data['sort'] ?? [], $source),
            groupBy: $this->resolveGroupBy($data['group_by'] ?? [], $source),
            parameters: $parameters,
        );
    }

    /**
     * Every field the document's blocks refer to, checked against the whitelist.
     *
     * @return array<int, string>
     */
    private function resolveFields(array $document, DataSource $source): array
    {
        $fields = $this->documentFields->referencedBy($document);

        foreach ($fields as $name) {
            if ($source->field($name) === null) {
                throw UnknownField::onSource($name, $source->key());
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function resolveFilters(array $filters, DataSource $source, array $parameters): array
    {
        $resolved = [];

        foreach ($filters as $filter) {
            $name = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? '=';

            if (! is_string($name) || ($field = $source->field($name)) === null) {
                throw UnknownField::onSource((string) $name, $source->key());
            }

            // Operators are constrained by the field's type, so a date field
            // cannot be filtered with "contains".
            if (! in_array($operator, $field->type->operators(), strict: true)) {
                throw UnknownField::operator((string) $operator, $name);
            }

            $condition = $this->resolveCondition($field, (string) $operator, $filter['value'] ?? null, $source, $parameters);

            if ($condition !== null) {
                $resolved[] = ['field' => $name, ...$condition];
            }
        }

        return $resolved;
    }

    /**
     * Resolve a filter's value, substituting parameter references.
     *
     * A filter that depends on a parameter with no value is left out rather than
     * matching nothing: an optional "status" parameter left empty should mean
     * every status, not none. A range with one bound becomes a one-sided
     * comparison.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{operator: string, value: mixed}|null
     */
    private function resolveCondition(Field $field, string $operator, mixed $value, DataSource $source, array $parameters): ?array
    {
        if (in_array($operator, ['in', 'not_in'], strict: true)) {
            $list = [];

            foreach (is_array($value) ? $value : [$value] as $item) {
                $item = $this->resolveValue($item, $source, $parameters);

                if (! blank($item)) {
                    $list[] = $item;
                }
            }

            return $list === [] ? null : ['operator' => $operator, 'value' => $list];
        }

        if ($operator === 'between') {
            $bounds = array_values(is_array($value) ? $value : [$value]);
            $lower = $this->resolveValue($bounds[0] ?? null, $source, $parameters);
            $upper = $this->resolveValue($bounds[1] ?? null, $source, $parameters);

            return match (true) {
                blank($lower) && blank($upper) => null,
                blank($upper) => $this->wholeDays($field, '>=', $lower),
                blank($lower) => $this->wholeDays($field, '<=', $upper),
                default => $this->wholeDays($field, 'between', [$lower, $upper]),
            };
        }

        $isReference = ParameterReference::parse($value) !== null;
        $value = $this->resolveValue($value, $source, $parameters);

        if ($isReference && blank($value)) {
            return null;
        }

        return $this->wholeDays($field, $operator, $value);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws UnknownParameter
     */
    private function resolveValue(mixed $value, DataSource $source, array $parameters): mixed
    {
        $name = ParameterReference::parse($value);

        if ($name === null) {
            return $value;
        }

        if (! array_key_exists($name, $source->parameters())) {
            throw UnknownParameter::onSource($name, $source->key());
        }

        return $parameters[$name] ?? null;
    }

    /**
     * Treat a plain date compared with a date-time column as the whole day.
     *
     * Without this, "to 30 September" becomes "before 30 September 00:00:00"
     * and silently drops every order placed on the last day of the range.
     *
     * @return array{operator: string, value: mixed}
     */
    private function wholeDays(Field $field, string $operator, mixed $value): array
    {
        if ($field->type !== FieldType::DateTime) {
            return ['operator' => $operator, 'value' => $value];
        }

        $startOfDay = fn (mixed $date): mixed => $this->isDateOnly($date) ? Carbon::parse($date)->startOfDay()->toDateTimeString() : $date;
        $endOfDay = fn (mixed $date): mixed => $this->isDateOnly($date) ? Carbon::parse($date)->endOfDay()->toDateTimeString() : $date;

        return match ($operator) {
            '>=', '<' => ['operator' => $operator, 'value' => $startOfDay($value)],
            '<=', '>' => ['operator' => $operator, 'value' => $endOfDay($value)],
            '=' => $this->isDateOnly($value)
                ? ['operator' => 'between', 'value' => [$startOfDay($value), $endOfDay($value)]]
                : ['operator' => '=', 'value' => $value],
            'between' => ['operator' => 'between', 'value' => [$startOfDay($value[0] ?? null), $endOfDay($value[1] ?? null)]],
            default => ['operator' => $operator, 'value' => $value],
        };
    }

    private function isDateOnly(mixed $value): bool
    {
        return is_string($value) && preg_match(self::DATE_ONLY, $value) === 1;
    }

    /**
     * @return array<int, array{field: string, direction: string}>
     */
    private function resolveSort(array $sort, DataSource $source): array
    {
        $resolved = [];

        foreach ($sort as $entry) {
            $name = $entry['field'] ?? null;

            if (! is_string($name) || $source->field($name) === null) {
                throw UnknownField::onSource((string) $name, $source->key());
            }

            $resolved[] = [
                'field' => $name,
                'direction' => ($entry['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ];
        }

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    private function resolveGroupBy(array $groupBy, DataSource $source): array
    {
        foreach ($groupBy as $name) {
            if (! is_string($name) || $source->field($name) === null) {
                throw UnknownField::onSource((string) $name, $source->key());
            }
        }

        return array_values($groupBy);
    }

    /**
     * Validate, default and normalise the parameters the source declared.
     *
     * Undeclared parameters are dropped rather than passed through, so a
     * document cannot smuggle extra values into a source.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidReportParameters
     */
    private function resolveParameters(DataSource $source, array $supplied): array
    {
        $rules = [];
        $values = [];

        foreach ($source->parameters() as $name => $parameter) {
            $rules[$name] = $parameter->rules();
            $values[$name] = $supplied[$name] ?? $parameter->default;
        }

        return $this->validated($source, $values, $rules);
    }

    /**
     * Like resolveParameters(), but nothing is required: a blank value falls
     * back to the declared default.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidReportParameters
     */
    private function resolvePreviewParameters(DataSource $source, array $supplied): array
    {
        $rules = [];
        $values = [];

        foreach ($source->parameters() as $name => $parameter) {
            $rules[$name] = ['nullable', ...array_values(array_diff($parameter->rules(), ['required', 'nullable']))];
            $values[$name] = blank($supplied[$name] ?? null) ? $parameter->default : $supplied[$name];
        }

        return $this->validated($source, $values, $rules);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws InvalidReportParameters
     */
    private function validated(DataSource $source, array $values, array $rules): array
    {
        if ($rules === []) {
            return [];
        }

        $validator = Validator::make($values, $rules);

        if ($validator->fails()) {
            throw new InvalidReportParameters($validator->errors()->messages());
        }

        foreach ($values as $name => $value) {
            $values[$name] = $this->normalize($source->parameters()[$name], $value);
        }

        return $values;
    }

    /**
     * Give every parameter one canonical shape, so a date typed as "30 Sep 2026"
     * filters exactly like "2026-09-30".
     */
    private function normalize(Parameter $parameter, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return match ($parameter->type) {
            FieldType::Date => Carbon::parse($value)->toDateString(),
            FieldType::DateTime => Carbon::parse($value)->toDateTimeString(),
            FieldType::Number, FieldType::Currency => $value + 0,
            FieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            FieldType::String => (string) $value,
        };
    }
}
