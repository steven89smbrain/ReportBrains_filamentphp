<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

use Illuminate\Support\Facades\Validator;
use ReportBrains\ReportDesigner\DataSources\Contracts\DataSource;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Schema\DocumentFields;

/**
 * Turns a report document plus runtime parameters into a validated ReportQuery.
 *
 * This is the boundary where a stored template stops being text and starts
 * touching data, so every field it names is checked against the source's
 * whitelist here. A source's `rows()` can then trust what it is handed.
 */
class ReportQueryFactory
{
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
     * @throws InvalidReportParameters
     */
    public function make(array $document, array $parameters = []): ReportQuery
    {
        $source = $this->registry->get($document['data']['source']);
        $data = $document['data'];

        return new ReportQuery(
            fields: $this->resolveFields($document, $source),
            filters: $this->resolveFilters($data['filters'] ?? [], $source),
            sort: $this->resolveSort($data['sort'] ?? [], $source),
            groupBy: $this->resolveGroupBy($data['group_by'] ?? [], $source),
            parameters: $this->resolveParameters($source, $parameters),
        );
    }

    public function sourceFor(array $document): DataSource
    {
        return $this->registry->get($document['data']['source']);
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
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    private function resolveFilters(array $filters, DataSource $source): array
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

            $resolved[] = [
                'field' => $name,
                'operator' => $operator,
                'value' => $filter['value'] ?? null,
            ];
        }

        return $resolved;
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
     * Validate and default the runtime parameters the source declared.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidReportParameters
     */
    private function resolveParameters(DataSource $source, array $supplied): array
    {
        $declared = $source->parameters();

        if ($declared === []) {
            return [];
        }

        $rules = [];
        $values = [];

        foreach ($declared as $name => $parameter) {
            $rules[$name] = $parameter->rules();
            $values[$name] = $supplied[$name] ?? $parameter->default;
        }

        $validator = Validator::make($values, $rules);

        if ($validator->fails()) {
            throw new InvalidReportParameters($validator->errors()->messages());
        }

        // Undeclared parameters are dropped rather than passed through, so a
        // document cannot smuggle extra values into a source.
        return $values;
    }
}
