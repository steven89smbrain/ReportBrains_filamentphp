<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Expressions;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;
use Throwable;

/**
 * Formats values for display.
 *
 * Deliberately does not use ext-intl: it is not guaranteed on a buyer's server,
 * and a report that silently changes its number format depending on the host's
 * PHP build is worse than one that is merely plain. Separators and formats come
 * from config instead.
 */
class ValueFormatter
{
    private const FORMATTERS = [
        'currency', 'number', 'integer', 'percent', 'date', 'datetime', 'upper', 'lower',
    ];

    /**
     * @throws InvalidExpression
     */
    public function format(mixed $value, string $formatter): mixed
    {
        if (! in_array($formatter, self::FORMATTERS, strict: true)) {
            throw InvalidExpression::unknownFormatter($formatter, self::FORMATTERS);
        }

        if ($value === null || $value === '') {
            return '';
        }

        return match ($formatter) {
            'currency' => $this->currency($value),
            'number' => $this->number($value),
            'integer' => $this->number($value, 0),
            'percent' => $this->number($value).$this->config('percent_suffix', '%'),
            'date' => $this->date($value, $this->config('date_format', 'Y-m-d')),
            'datetime' => $this->date($value, $this->config('datetime_format', 'Y-m-d H:i')),
            'upper' => mb_strtoupper((string) $value),
            'lower' => mb_strtolower((string) $value),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function available(): array
    {
        return self::FORMATTERS;
    }

    private function currency(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $amount = $this->number($value, (int) $this->config('currency_decimals', 2));
        $symbol = (string) $this->config('currency_symbol', '$');

        return $this->config('currency_symbol_after', false)
            ? $amount.$symbol
            : $symbol.$amount;
    }

    private function number(mixed $value, ?int $decimals = null): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        return number_format(
            (float) $value,
            $decimals ?? (int) $this->config('decimals', 2),
            (string) $this->config('decimal_separator', '.'),
            (string) $this->config('thousands_separator', ','),
        );
    }

    private function date(mixed $value, string $format): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (Throwable) {
            // A value that is not a date is shown as-is rather than blanking a
            // cell — an unexpected value is more useful than an empty one.
            return (string) $value;
        }
    }

    private function config(string $key, mixed $default): mixed
    {
        return config("report-designer.formatting.{$key}", $default);
    }
}
