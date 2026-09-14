<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    Pdf::fake();

    $this->renderer = new PdfRenderer;

    $this->report = fn (array $page = [], array $extraBands = []) => app(ReportCompiler::class)->compile([
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders & Co',
        'data' => ['source' => 'orders'],
        'page' => $page,
        'bands' => [
            'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'Orders']],
            'detail' => [['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']]]],
            ...$extraBands,
        ],
    ], [['invoice_no' => 'INV-1'], ['invoice_no' => 'INV-2']]);

    $this->withPageBands = [
        'page_header' => [['type' => 'text', 'content' => 'Orders report']],
        'page_footer' => [['type' => 'text', 'content' => 'Page {{ page.number }} of {{ page.total }}']],
    ];
});

it('prints the same report body the preview shows', function () {
    $builder = $this->renderer->builder(($this->report)());

    expect($builder->html)
        ->toContain('<title>Orders &amp; Co</title>')
        ->toContain('<h1>Orders</h1>')
        ->toContain('<td>INV-2</td>');
});

it('defaults to A4 portrait with 15 mm margins', function () {
    $builder = $this->renderer->builder(($this->report)());

    expect($builder->format)->toBe('a4')
        ->and($builder->orientation)->toBe(Orientation::Portrait->value)
        ->and($builder->margins)->toBe(['top' => 15.0, 'right' => 15.0, 'bottom' => 15.0, 'left' => 15.0, 'unit' => 'mm']);
});

it('applies the paper size, orientation and margins the document sets', function () {
    $builder = $this->renderer->builder(($this->report)([
        'size' => 'Letter',
        'orientation' => 'landscape',
        'margin' => ['top' => 10, 'left' => 20],
    ]));

    expect($builder->format)->toBe('letter')
        ->and($builder->orientation)->toBe(Orientation::Landscape->value)
        ->and($builder->margins)->toBe(['top' => 10.0, 'right' => 15.0, 'bottom' => 15.0, 'left' => 20.0, 'unit' => 'mm']);
});

describe('page header and footer', function () {
    it('turns the page bands into page templates with live page numbers', function () {
        $builder = $this->renderer->builder(($this->report)([], $this->withPageBands));

        expect($builder->headerHtml)->toContain('Orders report')
            ->and($builder->footerHtml)->toContain('Page <span class="pageNumber"></span> of <span class="totalPages"></span>');
    });

    it('keeps the page bands out of the body', function () {
        $builder = $this->renderer->builder(($this->report)([], $this->withPageBands));

        expect($builder->html)->not->toContain('Orders report')->not->toContain('pageNumber');
    });

    it('leaves room for them when the margins are not set', function () {
        $builder = $this->renderer->builder(($this->report)([], $this->withPageBands));

        expect($builder->margins['top'])->toBe(25.0)->and($builder->margins['bottom'])->toBe(25.0);
    });

    it('keeps a margin the document sets explicitly', function () {
        $builder = $this->renderer->builder(($this->report)(['margin' => ['top' => 12]], $this->withPageBands));

        expect($builder->margins['top'])->toBe(12.0);
    });

    it('escapes report data inside them', function () {
        $builder = $this->renderer->builder(($this->report)([], [
            'page_header' => [['type' => 'text', 'content' => '<script>alert(1)</script>']],
        ]));

        expect($builder->headerHtml)->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');
    });

    it('sends no page templates when those bands are unused', function () {
        $builder = $this->renderer->builder(($this->report)());

        expect($builder->headerHtml)->toBeNull()->and($builder->footerHtml)->toBeNull();
    });
});

describe('driver', function () {
    it('uses the driver set in the plugin config', function () {
        config()->set('report-designer.pdf.driver', 'gotenberg');

        $builder = $this->renderer->builder(($this->report)());

        expect((fn () => $this->driverName)->call($builder))->toBe('gotenberg');
    });

    it('leaves the choice to laravel-pdf when no driver is configured', function () {
        config()->set('report-designer.pdf.driver', null);

        $builder = $this->renderer->builder(($this->report)());

        expect((fn () => $this->driverName)->call($builder))->toBeNull();
    });
});

it('produces PDF content', function () {
    expect($this->renderer->render(($this->report)()))->not->toBe('');

    Pdf::assertSee(['INV-1', 'INV-2']);
});

it('reports its extension and mime type', function () {
    expect($this->renderer->extension())->toBe('pdf')
        ->and($this->renderer->mimeType())->toBe('application/pdf');
});
