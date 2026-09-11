<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFeature;
use ReportBrains\ReportDesigner\Schema\BandName;
use ReportBrains\ReportDesigner\Schema\BlockType;

beforeEach(function () {
    $this->compiler = app(ReportCompiler::class);

    $this->rows = [
        ['invoice_no' => 'INV-1', 'total' => 100, 'branch' => 'North'],
        ['invoice_no' => 'INV-2', 'total' => 250, 'branch' => 'South'],
        ['invoice_no' => 'INV-3', 'total' => 50, 'branch' => 'North'],
    ];

    $this->document = fn (array $bands, array $data = []): array => [
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => array_merge(['source' => 'orders'], $data),
        'bands' => $bands,
    ];
});

it('renders a header band once, not once per row', function () {
    $report = $this->compiler->compile(($this->document)([
        'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'Orders']],
    ]), $this->rows);

    expect($report->firstBand(BandName::DocumentHeader)->blocks)->toHaveCount(1);
});

it('repeats a non-table detail block once per row', function () {
    $report = $this->compiler->compile(($this->document)([
        'detail' => [['type' => 'text', 'content' => '{{ invoice_no }}']],
    ]), $this->rows);

    $blocks = $report->firstBand(BandName::Detail)->blocks;

    expect($blocks)->toHaveCount(3)
        ->and(array_map(fn ($b) => $b->get('content'), $blocks))->toBe(['INV-1', 'INV-2', 'INV-3']);
});

it('renders a table once with every row, rather than one table per row', function () {
    $report = $this->compiler->compile(($this->document)([
        'detail' => [[
            'type' => 'table',
            'columns' => [['field' => 'invoice_no', 'label' => 'Invoice'], ['field' => 'total', 'label' => 'Total']],
        ]],
    ]), $this->rows);

    $blocks = $report->firstBand(BandName::Detail)->blocks;

    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]->type)->toBe(BlockType::Table)
        ->and($blocks[0]->get('rows'))->toHaveCount(3)
        ->and($blocks[0]->get('rows')[0])->toBe(['INV-1', '100']);
});

it('applies a column format to every cell', function () {
    $report = $this->compiler->compile(($this->document)([
        'detail' => [[
            'type' => 'table',
            'columns' => [['field' => 'total', 'label' => 'Total', 'format' => 'currency']],
        ]],
    ]), $this->rows);

    expect($report->firstBand(BandName::Detail)->blocks[0]->get('rows'))
        ->toBe([['$100.00'], ['$250.00'], ['$50.00']]);
});

it('computes an aggregate over every row in the document footer', function () {
    $report = $this->compiler->compile(($this->document)([
        'document_footer' => [['type' => 'text', 'content' => 'Total: {{ sum(total) }}']],
    ]), $this->rows);

    expect($report->firstBand(BandName::DocumentFooter)->blocks[0]->get('content'))->toBe('Total: 400');
});

describe('grouping', function () {
    it('emits header, detail and footer bands for each group', function () {
        $report = $this->compiler->compile(($this->document)([
            'group_header' => [['type' => 'heading', 'level' => 2, 'content' => '{{ group.value }}']],
            'detail' => [['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']]]],
            'group_footer' => [['type' => 'text', 'content' => 'Subtotal: {{ sum(total) }}']],
        ], ['group_by' => ['branch']]), $this->rows);

        $names = array_map(fn ($band) => $band->name->value, $report->bands);

        expect($names)->toBe([
            'group_header', 'detail', 'group_footer',
            'group_header', 'detail', 'group_footer',
        ]);
    });

    it('scopes each group subtotal to that group only', function () {
        $report = $this->compiler->compile(($this->document)([
            'group_header' => [['type' => 'heading', 'level' => 2, 'content' => '{{ group.value }}']],
            'group_footer' => [['type' => 'text', 'content' => '{{ sum(total) }}']],
        ], ['group_by' => ['branch']]), $this->rows);

        $subtotals = [];

        foreach ($report->bands as $band) {
            if ($band->name === BandName::GroupFooter) {
                $subtotals[] = $band->blocks[0]->get('content');
            }
        }

        // North: 100 + 50, South: 250
        expect($subtotals)->toBe(['150', '250']);
    });

    it('puts only that group\'s rows in its detail table', function () {
        $report = $this->compiler->compile(($this->document)([
            'detail' => [['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']]]],
        ], ['group_by' => ['branch']]), $this->rows);

        $tables = array_map(fn ($band) => $band->blocks[0]->get('rows'), $report->bands);

        expect($tables)->toBe([[['INV-1'], ['INV-3']], [['INV-2']]]);
    });

    it('refuses to group by more than one field rather than silently ignoring one', function () {
        $this->compiler->compile(
            ($this->document)(['detail' => []], ['group_by' => ['branch', 'invoice_no']]),
            $this->rows,
        );
    })->throws(UnsupportedFeature::class, 'groups by one field');
});

it('omits bands the document does not define', function () {
    $report = $this->compiler->compile(($this->document)([
        'detail' => [['type' => 'text', 'content' => '{{ invoice_no }}']],
    ]), $this->rows);

    expect($report->bands)->toHaveCount(1);
});

it('produces an empty detail band when there are no rows', function () {
    $report = $this->compiler->compile(($this->document)([
        'detail' => [['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']]]],
    ]), []);

    expect($report->rowCount)->toBe(0)
        ->and($report->firstBand(BandName::Detail)->blocks[0]->get('rows'))->toBe([]);
});

it('carries the page setup through for paginated renderers', function () {
    $document = ($this->document)(['detail' => []]);
    $document['page'] = ['size' => 'A4', 'orientation' => 'landscape'];

    expect($this->compiler->compile($document, $this->rows)->page)
        ->toBe(['size' => 'A4', 'orientation' => 'landscape']);
});
