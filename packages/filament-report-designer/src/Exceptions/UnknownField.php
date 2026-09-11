<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

/**
 * Raised when a report asks for something its data source never exposed.
 *
 * This is a security boundary, not a convenience check: stored templates are
 * user input, so a template naming `password_hash` must be refused rather than
 * quietly returning the column.
 */
class UnknownField extends RuntimeException
{
    public static function onSource(string $field, string $source): self
    {
        return new self("The data source [{$source}] does not expose a field named [{$field}].");
    }

    public static function operator(string $operator, string $field): self
    {
        return new self("The operator [{$operator}] is not allowed on field [{$field}].");
    }
}
