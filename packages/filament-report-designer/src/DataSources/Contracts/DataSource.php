<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources\Contracts;

use ReportBrains\ReportDesigner\DataSources\Field;
use ReportBrains\ReportDesigner\DataSources\Parameter;
use ReportBrains\ReportDesigner\DataSources\ReportQuery;

/**
 * Something a report can read rows from.
 *
 * The contract deliberately speaks in rows rather than query builders. An
 * Eloquent source is the only implementation today, but a source backed by a
 * database view, a stored procedure or a remote API has no query builder to
 * hand back — returning rows is the one shape all of them can satisfy.
 */
interface DataSource
{
    /**
     * The identifier a report document refers to in `data.source`.
     */
    public function key(): string;

    /**
     * Human-readable name, shown in the designer's source picker.
     */
    public function label(): string;

    /**
     * Every field this source exposes, keyed by field name.
     *
     * This is the whitelist: a report may only select, sort, group or filter on
     * fields returned here.
     *
     * @return array<string, Field>
     */
    public function fields(): array;

    public function field(string $name): ?Field;

    /**
     * Inputs this source accepts at run time, keyed by parameter name.
     *
     * @return array<string, Parameter>
     */
    public function parameters(): array;

    /**
     * Fetch rows for a query that has already been validated against this source.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function rows(ReportQuery $query): iterable;
}
