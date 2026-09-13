<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\Designer\ReportPreview;
use ReportBrains\ReportDesigner\Facades\ReportData;
use Tests\Fixtures\Order;

beforeEach(function () {
    createOrderTables();

    foreach (range(1, 3) as $i) {
        Order::create(['invoice_no' => "INV-{$i}", 'total' => $i * 100, 'branch' => 'North', 'secret_note' => 'hidden']);
    }

    ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('total', 'Total', FieldType::Currency)
            ->addParameter('branch', 'Branch', FieldType::String, required: true);
    });

    $this->preview = app(ReportPreview::class);

    $this->document = fn (array $overrides = []): array => array_replace([
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => ['source' => 'orders'],
        'bands' => ['detail' => [[
            'type' => 'table',
            'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']],
        ]]],
    ], $overrides);
});

afterEach(fn () => app(DataSourceRegistry::class)->flush());

it('renders the report against live rows', function () {
    expect($this->preview->html(($this->document)()))
        ->toContain('<td>INV-1</td>')
        ->toContain('<td>INV-3</td>');
});

it('renders without the required parameters a real run would demand', function () {
    expect($this->preview->html(($this->document)()))->toContain('<table>');
});

it('caps the rows it reads and says so', function () {
    config()->set('report-designer.preview.max_rows', 2);

    $html = $this->preview->html(($this->document)());

    expect($html)->toContain('Showing the first 2 rows.')->not->toContain('INV-3');
});

it('asks for a data source before one is chosen', function () {
    expect($this->preview->html(($this->document)(['data' => ['source' => '']])))
        ->toContain('Choose a data source');
});

it('explains a problem instead of throwing while the document is incomplete', function () {
    $html = $this->preview->html(($this->document)([
        'bands' => ['detail' => [['type' => 'text', 'content' => '{{ nonsense( }}']]],
    ]));

    expect($html)->toContain('rb-preview-message')->toContain('could not be parsed');
});

it('still refuses a column the source never exposed', function () {
    $html = $this->preview->html(($this->document)([
        'bands' => ['detail' => [['type' => 'table', 'columns' => [['field' => 'secret_note']]]]],
    ]));

    expect($html)->toContain('does not expose a field named [secret_note]')->not->toContain('hidden');
});

it('escapes the message it shows', function () {
    $html = $this->preview->html(($this->document)(['data' => ['source' => '<b>x</b>']]));

    expect($html)->not->toContain('<b>x</b>')->toContain('&lt;b&gt;');
});
