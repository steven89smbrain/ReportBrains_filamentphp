<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\HtmlRenderer;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;

beforeEach(function () {
    $this->compiler = app(ReportCompiler::class);

    $this->rows = [
        ['invoice_no' => 'INV-1', 'customer' => 'Acme', 'total' => 100],
        ['invoice_no' => 'INV-2', 'customer' => 'Globex', 'total' => 250],
    ];

    $this->document = [
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => ['source' => 'orders'],
        'bands' => [
            'document_header' => [
                ['type' => 'heading', 'level' => 1, 'content' => 'Orders'],
                ['type' => 'divider'],
            ],
            'detail' => [[
                'type' => 'table',
                'columns' => [
                    ['field' => 'invoice_no', 'label' => 'Invoice'],
                    ['field' => 'customer', 'label' => 'Customer'],
                    ['field' => 'total', 'label' => 'Total', 'align' => 'right', 'format' => 'currency'],
                ],
            ]],
            'document_footer' => [
                ['type' => 'text', 'content' => 'Total: {{ sum(total) }}', 'align' => 'right', 'bold' => true],
            ],
        ],
    ];

    $this->report = fn (?array $rows = null) => $this->compiler->compile($this->document, $rows ?? $this->rows);
});

describe('markdown', function () {
    beforeEach(fn () => $this->renderer = new MarkdownRenderer);

    it('renders the whole report', function () {
        expect($this->renderer->render(($this->report)()))->toBe(<<<'MD'
        # Orders

        ---

        | Invoice | Customer | Total |
        | --- | --- | ---: |
        | INV-1 | Acme | $100.00 |
        | INV-2 | Globex | $250.00 |

        **Total: 350**

        MD);
    });

    it('marks column alignment in the separator row', function () {
        expect($this->renderer->render(($this->report)()))->toContain('| --- | --- | ---: |');
    });

    it('escapes a pipe in data so it cannot split a column', function () {
        $output = $this->renderer->render(($this->report)([
            ['invoice_no' => 'INV-1', 'customer' => 'A | B', 'total' => 1],
        ]));

        expect($output)->toContain('| A \| B |');
    });

    it('escapes markdown control characters in data', function () {
        $output = $this->renderer->render(($this->report)([
            ['invoice_no' => '*not bold*', 'customer' => 'A', 'total' => 1],
        ]));

        expect($output)->toContain('\*not bold\*');
    });

    it('reports its extension and mime type', function () {
        expect($this->renderer->extension())->toBe('md')
            ->and($this->renderer->mimeType())->toBe('text/markdown');
    });
});

describe('html', function () {
    beforeEach(fn () => $this->renderer = new HtmlRenderer);

    it('renders a full document with the report title', function () {
        expect($this->renderer->render(($this->report)()))
            ->toContain('<!DOCTYPE html>')
            ->toContain('<title>Orders</title>');
    });

    it('renders the table with headers and rows', function () {
        $output = $this->renderer->render(($this->report)());

        expect($output)
            ->toContain('<th>Invoice</th>')
            ->toContain('<td>INV-1</td>')
            ->toContain('<td style="text-align:right">$100.00</td>');
    });

    it('wraps each band in its own section', function () {
        expect($this->renderer->render(($this->report)()))
            ->toContain('rb-band--document_header')
            ->toContain('rb-band--detail');
    });

    it('escapes data so it cannot become markup', function () {
        $output = $this->renderer->render(($this->report)([
            ['invoice_no' => '<script>alert(1)</script>', 'customer' => 'A', 'total' => 1],
        ]));

        expect($output)
            ->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;');
    });

    it('escapes a quote so it cannot break out of an attribute', function () {
        $output = $this->renderer->render(($this->report)([
            ['invoice_no' => '" onmouseover="alert(1)', 'customer' => 'A', 'total' => 1],
        ]));

        expect($output)->not->toContain('onmouseover="alert(1)"');
    });

    it('can render a fragment for embedding in the designer preview', function () {
        $output = (new HtmlRenderer(fragment: true))->render(($this->report)());

        expect($output)->not->toContain('<!DOCTYPE html>')->toContain('<table>');
    });
});
