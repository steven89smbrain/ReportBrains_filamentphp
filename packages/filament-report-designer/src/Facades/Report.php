<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Facades;

use Illuminate\Support\Facades\Facade;
use ReportBrains\ReportDesigner\Output\OutputFormats;
use ReportBrains\ReportDesigner\Output\PendingReport;
use ReportBrains\ReportDesigner\Output\ReportManager;

/**
 * @method static PendingReport template(string $key)
 * @method static PendingReport file(string $keyOrPath)
 * @method static PendingReport document(array<string, mixed> $document)
 * @method static OutputFormats formats()
 *
 * @see ReportManager
 */
class Report extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReportManager::class;
    }
}
