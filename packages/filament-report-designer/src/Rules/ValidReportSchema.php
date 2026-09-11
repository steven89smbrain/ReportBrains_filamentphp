<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

/**
 * Validates a report document supplied either as an array or as a JSON string.
 *
 * Reports the underlying schema errors rather than a generic "invalid" message,
 * since a document can fail for a dozen different reasons.
 */
class ValidReportSchema implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $fail('The report document is not valid JSON: '.$exception->getMessage());

                return;
            }
        }

        if (! is_array($value)) {
            $fail('The report document must be a JSON object.');

            return;
        }

        try {
            app(ReportSchema::class)->validate($value);
        } catch (InvalidReportSchema $exception) {
            foreach ($exception->messages() as $message) {
                $fail($message);
            }
        }
    }
}
