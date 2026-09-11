<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Compiler;

use ReportBrains\ReportDesigner\Schema\BandName;

class RenderedBand
{
    /**
     * @param  array<int, RenderedBlock>  $blocks
     */
    public function __construct(
        public readonly BandName $name,
        public readonly array $blocks,
    ) {}

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }
}
