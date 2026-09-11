<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

/**
 * A validated request for rows.
 *
 * Every field named here has already been checked against the source's
 * whitelist, and every parameter has been validated and cast. A source's
 * `rows()` may therefore use these values directly.
 */
class ReportQuery
{
    /**
     * @param  array<int, string>  $fields  Field names to read.
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters
     * @param  array<int, array{field: string, direction: string}>  $sort
     * @param  array<int, string>  $groupBy
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly array $fields = [],
        public readonly array $filters = [],
        public readonly array $sort = [],
        public readonly array $groupBy = [],
        public readonly array $parameters = [],
        public readonly ?int $limit = null,
    ) {}

    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }

    public function withLimit(?int $limit): self
    {
        return new self(
            $this->fields,
            $this->filters,
            $this->sort,
            $this->groupBy,
            $this->parameters,
            $limit,
        );
    }

    /**
     * Field names that must be read, including those only needed for sorting
     * and grouping.
     *
     * @return array<int, string>
     */
    public function requiredFields(): array
    {
        return array_values(array_unique([
            ...$this->fields,
            ...$this->groupBy,
            ...array_column($this->sort, 'field'),
        ]));
    }
}
