<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

class TemplateNotFound extends RuntimeException
{
    public static function key(string $key): self
    {
        return new self("No report template found for key [{$key}].");
    }

    public static function path(string $path): self
    {
        return new self("No report template file at [{$path}].");
    }

    public static function outsideTemplateDirectory(string $path): self
    {
        return new self("Refusing to read [{$path}]: it is outside the configured template directory.");
    }
}
