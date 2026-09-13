<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

class UnknownParameter extends RuntimeException
{
    public static function onSource(string $parameter, string $source): self
    {
        return new self("A filter refers to the parameter [{$parameter}], which the data source [{$source}] does not declare.");
    }
}
