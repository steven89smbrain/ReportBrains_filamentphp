<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\CsvRenderer;

beforeEach(function () {
    $this->csv = function (array $bands, array $rows, array $data = []): string {
        $report = app(ReportCompiler::class)->compile([
            'schema_version' => 1,
            'key' => 'orders',
            'title' => 'Orders',
            'data' => ['source' => 'orders', ...$data],
            'bands' => $bands,
        ], $rows);

        return (new CsvRenderer)->render($report);
    };

    $this->table = ['type' => 'table', 'columns' => [
        ['field' => 'invoice_no', 'label' => 'Invoice'],
        ['field' => 'total', 'label' => 'Total', 'format' => 'currency'],
    ]];

    $this->rows = [
        ['invoice_no' => 'INV-1', 'total' => 1250.5, 'branch' => 'North'],
        ['invoice_no' => 'INV-2', 'total' => 80, 'branch' => 'South'],
    ];

    $this->lines = fn (string $csv): array => explode("\n", trim(substr($csv, 3)));
});

it('starts with a byte-order mark so Excel reads UTF-8', function () {
    expect(($this->csv)(['detail' => [$this->table]], $this->rows))->toStartWith("\xEF\xBB\xBF");
});

it('writes the column labels and the raw values, not the formatted ones', function () {
    expect(($this->lines)(($this->csv)(['detail' => [$this->table]], $this->rows)))->toBe([
        'Invoice,Total',
        'INV-1,1250.5',
        'INV-2,80',
    ]);
});

it('adds a group column for a grouped report', function () {
    expect(($this->lines)(($this->csv)(['detail' => [$this->table]], $this->rows, ['group_by' => ['branch']])))->toBe([
        'Group,Invoice,Total',
        'North,INV-1,1250.5',
        'South,INV-2,80',
    ]);
});

it('exports only data tables, not headings or header tables', function () {
    $csv = ($this->csv)([
        'document_header' => [
            ['type' => 'heading', 'level' => 1, 'content' => 'Orders'],
            ['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Header table']]],
        ],
        'detail' => [$this->table],
    ], $this->rows);

    expect($csv)->not->toContain('Orders')->not->toContain('Header table');
});

it('writes dates in a sortable form', function () {
    $csv = ($this->csv)(
        ['detail' => [['type' => 'table', 'columns' => [['field' => 'ordered_at', 'label' => 'Ordered', 'format' => 'date']]]]],
        [['ordered_at' => new DateTimeImmutable('2026-09-14 08:30:00')]],
    );

    expect(($this->lines)($csv)[1])->toBe('"2026-09-14 08:30:00"');
});

it('neutralises values a spreadsheet would run as a formula', function () {
    $csv = ($this->csv)(['detail' => [$this->table]], [
        ['invoice_no' => '=HYPERLINK("http://evil.example","click")', 'total' => -5],
        ['invoice_no' => '@SUM(A1)', 'total' => '-12.5'],
    ]);

    expect(($this->lines)($csv))->toBe([
        'Invoice,Total',
        '"\'=HYPERLINK(""http://evil.example"",""click"")",-5',
        "'@SUM(A1),-12.5",
    ]);
});

it('reports its extension and mime type', function () {
    expect((new CsvRenderer)->extension())->toBe('csv')
        ->and((new CsvRenderer)->mimeType())->toBe('text/csv');
});
