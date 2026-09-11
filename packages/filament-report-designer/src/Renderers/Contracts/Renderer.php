<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers\Contracts;

use ReportBrains\ReportDesigner\Compiler\RenderedReport;

/**
 * Translates a compiled report into one output format.
 *
 * A renderer receives fully resolved data, so it needs no database, no
 * evaluator and no knowledge of the document format.
 */
interface Renderer
{
    public function render(RenderedReport $report): string;

    /**
     * File extension for this format, without the dot.
     */
    public function extension(): string;

    public function mimeType(): string;
}
