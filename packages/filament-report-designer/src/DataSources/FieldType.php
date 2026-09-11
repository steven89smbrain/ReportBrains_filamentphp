<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

/**
 * The kinds of value a field or parameter can hold.
 *
 * The type drives three things: which input the parameter form renders, which
 * operators a filter may use, and how a renderer formats the value.
 */
enum FieldType: string
{
    case String = 'string';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case DateTime = 'datetime';
    case Boolean = 'boolean';

    /**
     * Filter operators that make sense for this type.
     *
     * Anything outside this list is rejected, so a filter cannot smuggle in an
     * operator the source never intended to expose.
     *
     * @return array<int, string>
     */
    public function operators(): array
    {
        return match ($this) {
            self::String => ['=', '!=', 'contains', 'starts_with', 'ends_with', 'in', 'not_in'],
            self::Number, self::Currency => ['=', '!=', '<', '<=', '>', '>=', 'between', 'in', 'not_in'],
            self::Date, self::DateTime => ['=', '!=', '<', '<=', '>', '>=', 'between'],
            self::Boolean => ['='],
        };
    }

    public function isNumeric(): bool
    {
        return in_array($this, [self::Number, self::Currency], strict: true);
    }

    public function isTemporal(): bool
    {
        return in_array($this, [self::Date, self::DateTime], strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
