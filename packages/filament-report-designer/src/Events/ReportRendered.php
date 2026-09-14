<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A queued report finished rendering and was stored.
 *
 * Listen for this to notify the person who asked for it, or to attach the file
 * to an email.
 */
class ReportRendered
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $templateKey,
        public readonly string $format,
        public readonly string $path,
        public readonly ?string $disk,
        public readonly int|string|null $userId,
    ) {}
}
