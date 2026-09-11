<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

/**
 * Thrown when a report document fails schema validation.
 */
class InvalidReportSchema extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors  Keyed by the failing path, e.g. "bands.detail.0.columns".
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'The report schema is invalid.',
    ) {
        parent::__construct($message);
    }

    /**
     * Every failure message, flattened.
     *
     * @return array<int, string>
     */
    public function messages(): array
    {
        return array_merge(...array_values($this->errors)) ?: [];
    }

    public function summary(): string
    {
        return implode(' ', $this->messages());
    }
}
