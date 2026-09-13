<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Designer;

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Exceptions\InvalidExpression;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFeature;
use ReportBrains\ReportDesigner\Renderers\HtmlRenderer;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

/**
 * Renders the live preview shown beside the designer.
 *
 * A document being edited is incomplete most of the time, so problems are
 * reported as a message in the preview panel rather than thrown — a half-typed
 * expression should never turn the editor into an error page.
 */
class ReportPreview
{
    public function __construct(
        private readonly ReportSchema $schema,
        private readonly ReportQueryFactory $queries,
        private readonly ReportCompiler $compiler,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public function html(array $document): string
    {
        if (blank($document['data']['source'] ?? null)) {
            return $this->message('Choose a data source to see a preview.');
        }

        try {
            $this->schema->validate($document);

            $limit = (int) config('report-designer.preview.max_rows', 25);
            $query = $this->queries->preview($document, $limit);
            $rows = $this->queries->sourceFor($document)->rows($query);
            $report = $this->compiler->compile($document, $rows, $query->parameters);
        } catch (InvalidReportSchema $exception) {
            return $this->message($exception->summary());
        } catch (UnknownDataSource|UnknownField|InvalidExpression|UnsupportedFeature $exception) {
            return $this->message($exception->getMessage());
        }

        $notice = count($rows) >= $limit
            ? $this->note("Showing the first {$limit} rows.")
            : '';

        return $notice.(new HtmlRenderer(fragment: true))->render($report);
    }

    private function message(string $text): string
    {
        return sprintf(
            '<div class="rb-preview-message" role="status">%s</div>',
            htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    private function note(string $text): string
    {
        return sprintf(
            '<p class="rb-preview-note">%s</p>',
            htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        );
    }
}
