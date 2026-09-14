<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use InvalidArgumentException;

class UnsupportedFormat extends InvalidArgumentException
{
    /**
     * @param  array<int, string>  $available
     */
    public static function named(string $format, array $available): self
    {
        return new self(sprintf(
            'No output format named [%s]. Available formats: %s.',
            $format,
            implode(', ', $available),
        ));
    }
}
