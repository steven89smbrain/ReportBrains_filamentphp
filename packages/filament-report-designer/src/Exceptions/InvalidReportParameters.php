<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

class InvalidReportParameters extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The report parameters are invalid: '.implode(' ', array_merge(...array_values($errors))));
    }
}
