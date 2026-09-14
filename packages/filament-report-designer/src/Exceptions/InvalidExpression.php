<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

class InvalidExpression extends RuntimeException
{
    public static function syntax(string $expression): self
    {
        return new self("The expression [{$expression}] could not be parsed.");
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function unknownFunction(string $name, array $allowed): self
    {
        return new self(sprintf(
            'Unknown function [%s] in an expression. Allowed functions: %s.',
            $name,
            implode(', ', $allowed),
        ));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function unknownFormatter(string $name, array $allowed): self
    {
        return new self(sprintf(
            'Unknown formatter [%s] in an expression. Allowed formatters: %s.',
            $name,
            implode(', ', $allowed),
        ));
    }

    public static function pageNumberOutsidePageBand(string $reference): self
    {
        return new self("[{$reference}] is only available in the page header and page footer, where a PDF has page numbers.");
    }

    public static function unknownReference(string $reference): self
    {
        return new self("The expression refers to [{$reference}], which is not a field, parameter or group value available here.");
    }
}
