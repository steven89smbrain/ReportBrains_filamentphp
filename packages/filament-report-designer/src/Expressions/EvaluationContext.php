<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Expressions;

/**
 * Everything an expression is allowed to see.
 *
 * Expressions cannot reach outside this object — no container, no facades, no
 * request. Whatever is not here cannot be referenced.
 */
class EvaluationContext
{
    /**
     * @param  array<string, mixed>  $row  The current row, keyed by field name.
     * @param  array<int, array<string, mixed>>  $rows  Rows in scope for aggregates.
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $group  Current group's field and value.
     * @param  bool  $paginated  Whether this is a page header or footer, where page numbers exist.
     */
    public function __construct(
        public readonly array $row = [],
        public readonly array $rows = [],
        public readonly array $parameters = [],
        public readonly array $group = [],
        public readonly bool $paginated = false,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function withRow(array $row): self
    {
        return new self($row, $this->rows, $this->parameters, $this->group, $this->paginated);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function withRows(array $rows): self
    {
        return new self($this->row, $rows, $this->parameters, $this->group, $this->paginated);
    }

    /**
     * @param  array<string, mixed>  $group
     */
    public function withGroup(array $group): self
    {
        return new self($this->row, $this->rows, $this->parameters, $group, $this->paginated);
    }

    public function withPagination(): self
    {
        return new self($this->row, $this->rows, $this->parameters, $this->group, true);
    }
}
