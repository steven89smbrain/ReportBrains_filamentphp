<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

/**
 * The `{{ params.name }}` form a filter value takes when it should come from a
 * report parameter rather than being fixed in the template.
 *
 * Only a value that is exactly one reference counts. Text that merely contains
 * one ("over {{ params.min }}") is compared literally, so a parameter can never
 * be spliced into part of a value.
 */
final class ParameterReference
{
    private const PATTERN = '/^\s*\{\{\s*params\.([a-z_][a-z0-9_]*)\s*\}\}\s*$/';

    public static function to(string $parameter): string
    {
        return '{{ params.'.$parameter.' }}';
    }

    /**
     * The parameter name a value refers to, or null when it is a fixed value.
     */
    public static function parse(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
