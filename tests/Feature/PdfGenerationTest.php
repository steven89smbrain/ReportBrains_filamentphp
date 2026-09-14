<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;

/**
 * Prints a real PDF through Chrome.
 *
 * Skipped where no Chrome or Chromium binary exists, such as a CI runner without
 * a browser. The renderer's configuration is covered by PdfRendererTest either
 * way; this proves the whole pipeline produces a real, paginated document.
 */
if (! function_exists('reportDesignerChromeBinary')) {
    function reportDesignerChromeBinary(): ?string
    {
        $candidates = [
            config('laravel-pdf.chrome.chrome_binary'),
            getenv('CHROME_PATH') ?: null,
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}

it('prints a multi-page PDF with page numbers through Chrome', function () {
    $binary = reportDesignerChromeBinary();

    if ($binary === null) {
        $this->markTestSkipped('No Chrome or Chromium binary is available.');
    }

    config()->set('report-designer.pdf.driver', 'chrome');
    config()->set('laravel-pdf.chrome.chrome_binary', $binary);

    $rows = array_map(fn (int $i): array => ['invoice_no' => "INV-{$i}", 'total' => $i * 1000], range(1, 150));

    $report = app(ReportCompiler::class)->compile([
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => ['source' => 'orders'],
        'page' => ['size' => 'A4', 'orientation' => 'portrait'],
        'bands' => [
            'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'Orders']],
            'detail' => [['type' => 'table', 'columns' => [
                ['field' => 'invoice_no', 'label' => 'Invoice'],
                ['field' => 'total', 'label' => 'Total', 'align' => 'right', 'format' => 'currency'],
            ]]],
            'page_footer' => [['type' => 'text', 'content' => 'Page {{ page.number }} of {{ page.total }}', 'align' => 'center']],
        ],
    ], $rows);

    $pdf = (new PdfRenderer)->render($report);

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBeGreaterThan(1);
})->group('pdf');
