<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;

/**
 * Checks that a document's `data.source` is actually registered.
 *
 * Applied when authoring so the mistake surfaces on the form, rather than at
 * render time when someone is waiting for a report.
 */
class RegisteredDataSource implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return; // ValidReportSchema reports malformed JSON.
            }
        }

        $source = $value['data']['source'] ?? null;

        if (! is_string($source)) {
            return; // ValidReportSchema reports a missing source.
        }

        $registry = app(DataSourceRegistry::class);

        if ($registry->has($source)) {
            return;
        }

        $registered = $registry->keys();

        $fail($registered === []
            ? "The data source [{$source}] is not registered. No data sources are registered yet."
            : "The data source [{$source}] is not registered. Available: ".implode(', ', $registered).'.');
    }
}
