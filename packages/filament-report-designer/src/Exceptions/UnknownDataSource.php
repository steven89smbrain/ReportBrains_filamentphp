<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

class UnknownDataSource extends RuntimeException
{
    /**
     * @param  array<int, string>  $registered
     */
    public static function key(string $key, array $registered = []): self
    {
        $message = "No data source is registered under [{$key}].";

        if ($registered !== []) {
            $message .= ' Registered sources: '.implode(', ', $registered).'.';
        }

        return new self($message);
    }
}
