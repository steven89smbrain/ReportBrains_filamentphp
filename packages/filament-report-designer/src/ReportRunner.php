<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Exceptions\UnknownParameter;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFeature;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;

/**
 * Runs a report document end to end: binds it to its data source, reads the
 * rows, compiles it, and hands the result to a renderer.
 */
class ReportRunner
{
    public function __construct(
        private readonly ReportQueryFactory $queries,
        private readonly ReportCompiler $compiler,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     *
     * @throws UnknownDataSource|UnknownField|UnknownParameter|InvalidReportParameters|InvalidExpression|UnsupportedFeature
     */
    public function compile(array $document, array $parameters = []): RenderedReport
    {
        $query = $this->queries->make($document, $parameters);
        $rows = $this->queries->sourceFor($document)->rows($query);

        return $this->compiler->compile($document, $rows, $query->parameters);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     */
    public function render(array $document, Renderer $renderer, array $parameters = []): string
    {
        return $renderer->render($this->compile($document, $parameters));
    }
}
