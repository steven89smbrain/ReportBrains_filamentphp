<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Expressions\PageToken;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\Schema\BandName;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Renders a report as PDF by printing its HTML with a browser engine.
 *
 * The body is the same HTML the designer previews, printed through
 * spatie/laravel-pdf. Which engine does the printing is configuration
 * (`report-designer.pdf.driver`); Chromium-based drivers are recommended because
 * they lay a page out exactly as the preview does.
 */
class PdfRenderer implements Renderer
{
    private const DEFAULT_MARGIN_MM = 15;

    /**
     * Chrome draws page headers and footers inside the page margin, so a margin
     * left at the default would clip them.
     */
    private const MARGIN_FOR_HEADER_MM = 25;

    public function render(RenderedReport $report): string
    {
        return $this->builder($report)->generatePdfContent();
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }

    /**
     * The configured PDF builder, before anything is printed.
     */
    public function builder(RenderedReport $report): PdfBuilder
    {
        $html = new HtmlRenderer(fragment: true);
        $header = $this->pageBand($report, BandName::PageHeader, $html);
        $footer = $this->pageBand($report, BandName::PageFooter, $html);

        $builder = Pdf::html($this->document($report, $html->render($report)));

        $driver = config('report-designer.pdf.driver');

        if (is_string($driver) && $driver !== '') {
            $builder->driver($driver);
        }

        $page = $report->page;
        $margin = $page['margin'] ?? [];

        $builder->format(strtolower((string) ($page['size'] ?? 'A4')));

        ($page['orientation'] ?? 'portrait') === 'landscape'
            ? $builder->landscape()
            : $builder->portrait();

        $builder->margins(
            top: (float) ($margin['top'] ?? ($header === null ? self::DEFAULT_MARGIN_MM : self::MARGIN_FOR_HEADER_MM)),
            right: (float) ($margin['right'] ?? self::DEFAULT_MARGIN_MM),
            bottom: (float) ($margin['bottom'] ?? ($footer === null ? self::DEFAULT_MARGIN_MM : self::MARGIN_FOR_HEADER_MM)),
            left: (float) ($margin['left'] ?? self::DEFAULT_MARGIN_MM),
            unit: 'mm',
        );

        if ($header !== null) {
            $builder->headerHtml($header);
        }

        if ($footer !== null) {
            $builder->footerHtml($footer);
        }

        return $builder;
    }

    private function document(RenderedReport $report, string $body): string
    {
        $title = htmlspecialchars($report->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            ."<title>{$title}</title><style>{$this->styles()}</style></head>"
            ."<body>{$body}</body></html>";
    }

    private function styles(): string
    {
        return 'html{-webkit-print-color-adjust:exact;print-color-adjust:exact}'
            .'body{margin:0;color:#111;font-size:10pt;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}'
            .'h1{font-size:18pt;margin:0 0 8pt}h2{font-size:14pt;margin:12pt 0 6pt}h3{font-size:12pt;margin:10pt 0 4pt}'
            .'h4,h5,h6{font-size:10pt;margin:8pt 0 4pt}p{margin:4pt 0}'
            .'hr{border:0;border-top:1px solid #d4d4d8;margin:8pt 0}'
            .'table{border-collapse:collapse;width:100%;margin:4pt 0}'
            // Repeat a table's header row on every page it spans, and keep rows whole.
            .'thead{display:table-header-group}tr{break-inside:avoid}'
            .'th,td{border:1px solid #d4d4d8;padding:3pt 5pt;text-align:left;vertical-align:top}'
            .'th{background:#f4f4f5;font-weight:600}'
            .'.rb-band--group_header{break-after:avoid}';
    }

    /**
     * The page header or footer as a Chrome header template, or null when the
     * band is not used.
     */
    private function pageBand(RenderedReport $report, BandName $name, HtmlRenderer $html): ?string
    {
        $band = $report->firstBand($name);

        if ($band === null || $band->isEmpty()) {
            return null;
        }

        $content = PageToken::toChromeSpans($html->renderBands([$band]));

        // Header and footer templates are rendered in isolation: the page's
        // stylesheet does not reach them, and Chrome's default font size there is
        // too small to read.
        return '<div style="width:100%;padding:0 '.self::DEFAULT_MARGIN_MM.'mm;color:#444;font-size:9px;'
            .'font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;-webkit-print-color-adjust:exact">'
            .'<style>h1,h2,h3,h4,h5,h6,p{margin:0}hr{border:0;border-top:1px solid #ccc;margin:2px 0}'
            .'table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:1px 3px}</style>'
            .$content
            .'</div>';
    }
}
