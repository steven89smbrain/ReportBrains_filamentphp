<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Compiler;

use ReportBrains\ReportDesigner\Schema\BandName;

class RenderedBand
{
    /**
     * @param  array<int, RenderedBlock>  $blocks
     * @param  string|null  $group  The grouping value, when the band belongs to a group.
     */
    public function __construct(
        public readonly BandName $name,
        public readonly array $blocks,
        public readonly ?string $group = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }
}
