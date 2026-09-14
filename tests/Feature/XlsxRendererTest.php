<?php

declare(strict_types=1);

use OpenSpout\Reader\XLSX\Reader;
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\Renderers\XlsxRenderer;

beforeEach(function () {
    $this->sheet = function (array $rows, array $data = []): array {
        $report = app(ReportCompiler::class)->compile([
            'schema_version' => 1,
            'key' => 'orders',
            'title' => 'Orders',
            'data' => ['source' => 'orders', ...$data],
            'bands' => ['detail' => [['type' => 'table', 'columns' => [
                ['field' => 'invoice_no', 'label' => 'Invoice'],
                ['field' => 'total', 'label' => 'Total', 'format' => 'currency'],
                ['field' => 'ordered_at', 'label' => 'Ordered', 'format' => 'date'],
            ]]]],
        ], $rows);

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($path, (new XlsxRenderer)->render($report));

        $reader = new Reader;
        $reader->open($path);
        $values = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $values[] = $row->toArray();
            }

            break;
        }

        $reader->close();
        unlink($path);

        return $values;
    };

    $this->rows = [
        ['invoice_no' => 'INV-1', 'total' => '1250.50', 'ordered_at' => new DateTimeImmutable('2026-09-14 08:30:00'), 'branch' => 'North'],
        ['invoice_no' => 'INV-2', 'total' => 80, 'ordered_at' => null, 'branch' => 'South'],
    ];
});

it('produces a workbook with the column labels and the data rows', function () {
    $sheet = ($this->sheet)($this->rows);

    expect($sheet[0])->toBe(['Invoice', 'Total', 'Ordered'])
        ->and($sheet)->toHaveCount(3)
        ->and($sheet[1][0])->toBe('INV-1');
});

it('keeps numbers as numbers, including decimal strings from the database', function () {
    $sheet = ($this->sheet)($this->rows);

    expect($sheet[1][1])->toBe(1250.5)
        ->and($sheet[2][1])->toBe(80);
});

it('keeps dates as real dates', function () {
    $sheet = ($this->sheet)($this->rows);

    expect($sheet[1][2])->toBeInstanceOf(DateTimeInterface::class)
        ->and($sheet[1][2]->format('Y-m-d H:i'))->toBe('2026-09-14 08:30');
});

it('adds a group column for a grouped report', function () {
    $sheet = ($this->sheet)($this->rows, ['group_by' => ['branch']]);

    expect($sheet[0])->toBe(['Group', 'Invoice', 'Total', 'Ordered'])
        ->and($sheet[1][0])->toBe('North');
});

it('stores formula-looking text as text, never as a formula', function () {
    $sheet = ($this->sheet)([
        ['invoice_no' => '=HYPERLINK("http://evil.example","click")', 'total' => 1, 'ordered_at' => null],
    ]);

    expect($sheet[1][0])->toBe('=HYPERLINK("http://evil.example","click")');
});

it('reports its extension and mime type', function () {
    expect((new XlsxRenderer)->extension())->toBe('xlsx')
        ->and((new XlsxRenderer)->mimeType())->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
