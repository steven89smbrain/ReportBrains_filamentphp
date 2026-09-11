<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Facades\ReportData;
use Tests\Fixtures\Order;

beforeEach(function () {
    ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('total', 'Total', FieldType::Currency)
            ->addField('branch', 'Branch')
            ->addParameter('min_total', 'Minimum total', FieldType::Number)
            ->addParameter('branch', 'Branch', FieldType::String, required: true);
    });

    $this->factory = app(ReportQueryFactory::class);

    // Built explicitly rather than through validReportDocument(): that helper
    // merges recursively, so an overridden band would keep the fixture's
    // columns alongside the ones a test is actually about.
    $this->document = fn (array $data = [], array $columns = []): array => [
        'schema_version' => 1,
        'key' => 'orders-report',
        'title' => 'Orders Report',
        'data' => array_merge(['source' => 'orders'], $data),
        'bands' => ['detail' => [[
            'type' => 'table',
            'columns' => $columns ?: [['field' => 'invoice_no', 'label' => 'Invoice']],
        ]]],
    ];
});

afterEach(fn () => app(DataSourceRegistry::class)->flush());

it('collects the fields a document actually refers to', function () {
    $query = $this->factory->make(($this->document)([], [
        ['field' => 'invoice_no', 'label' => 'Invoice'],
        ['field' => 'total', 'label' => 'Total'],
    ]), ['branch' => 'North']);

    expect($query->fields)->toBe(['invoice_no', 'total']);
});

it('rejects a document whose source is not registered', function () {
    $this->factory->make(($this->document)(['source' => 'payroll']), []);
})->throws(UnknownDataSource::class);

it('rejects a column the source never exposed', function () {
    $this->factory->make(($this->document)([], [
        ['field' => 'secret_note', 'label' => 'Secret'],
    ]), ['branch' => 'North']);
})->throws(UnknownField::class, 'does not expose a field named [secret_note]');

it('rejects sorting by a field the source never exposed', function () {
    $this->factory->make(
        ($this->document)(['sort' => [['field' => 'secret_note', 'dir' => 'asc']]]),
        ['branch' => 'North'],
    );
})->throws(UnknownField::class);

it('rejects grouping by a field the source never exposed', function () {
    $this->factory->make(
        ($this->document)(['group_by' => ['secret_note']]),
        ['branch' => 'North'],
    );
})->throws(UnknownField::class);

it('rejects a filter on a field the source never exposed', function () {
    $this->factory->make(
        ($this->document)(['filters' => [['field' => 'secret_note', 'operator' => '=', 'value' => 'x']]]),
        ['branch' => 'North'],
    );
})->throws(UnknownField::class);

it('rejects an operator the field type does not allow', function () {
    // "contains" is a string operator; total is currency.
    $this->factory->make(
        ($this->document)(['filters' => [['field' => 'total', 'operator' => 'contains', 'value' => '1']]]),
        ['branch' => 'North'],
    );
})->throws(UnknownField::class, 'The operator [contains] is not allowed');

it('normalises sort direction', function () {
    $query = $this->factory->make(
        ($this->document)(['sort' => [['field' => 'total', 'dir' => 'desc'], ['field' => 'branch']]]),
        ['branch' => 'North'],
    );

    expect($query->sort)->toBe([
        ['field' => 'total', 'direction' => 'desc'],
        ['field' => 'branch', 'direction' => 'asc'],
    ]);
});

it('requires the parameters the source declared', function () {
    $this->factory->make(($this->document)(), []);
})->throws(InvalidReportParameters::class);

it('reports which parameter was missing', function () {
    expect(fn () => $this->factory->make(($this->document)(), []))
        ->toThrow(fn (InvalidReportParameters $e) => expect($e->errors)->toHaveKey('branch'));
});

it('rejects a parameter of the wrong type', function () {
    $this->factory->make(($this->document)(), ['branch' => 'North', 'min_total' => 'not a number']);
})->throws(InvalidReportParameters::class);

it('drops parameters the source never declared', function () {
    $query = $this->factory->make(
        ($this->document)(),
        ['branch' => 'North', 'is_admin' => true],
    );

    expect($query->parameters)->toHaveKey('branch')->not->toHaveKey('is_admin');
});

it('includes sort and group fields in the fields that must be read', function () {
    $query = $this->factory->make(
        ($this->document)(['sort' => [['field' => 'total', 'dir' => 'asc']], 'group_by' => ['branch']]),
        ['branch' => 'North'],
    );

    expect($query->requiredFields())->toEqualCanonicalizing(['invoice_no', 'total', 'branch']);
});
