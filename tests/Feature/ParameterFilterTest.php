<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportParameters;
use ReportBrains\ReportDesigner\Exceptions\UnknownParameter;
use ReportBrains\ReportDesigner\Facades\ReportData;
use Tests\Fixtures\Order;

beforeEach(function () {
    createOrderTables();

    Order::create(['invoice_no' => 'INV-1', 'total' => 100, 'branch' => 'North', 'ordered_at' => '2026-09-01 09:00:00']);
    Order::create(['invoice_no' => 'INV-2', 'total' => 250, 'branch' => 'South', 'ordered_at' => '2026-09-15 23:30:00']);
    Order::create(['invoice_no' => 'INV-3', 'total' => 50, 'branch' => 'North', 'ordered_at' => '2026-09-30 18:45:00']);
    Order::create(['invoice_no' => 'INV-4', 'total' => 400, 'branch' => 'South', 'ordered_at' => '2026-10-02 08:00:00']);

    ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('ordered_at', 'Order date', FieldType::DateTime)
            ->addField('branch', 'Branch')
            ->addField('total', 'Total', FieldType::Currency)
            ->addParameter('from', 'From date', FieldType::Date)
            ->addParameter('to', 'To date', FieldType::Date)
            ->addParameter('branch', 'Branch')
            ->addParameter('min_total', 'Minimum total', FieldType::Number);
    });

    $this->factory = app(ReportQueryFactory::class);

    $this->document = fn (array $filters): array => [
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => ['source' => 'orders', 'filters' => $filters, 'sort' => [['field' => 'invoice_no', 'dir' => 'asc']]],
        'bands' => ['detail' => [['type' => 'table', 'columns' => [['field' => 'invoice_no', 'label' => 'Invoice']]]]],
    ];

    $this->invoices = function (array $filters, array $parameters = []): array {
        $document = ($this->document)($filters);
        $query = $this->factory->make($document, $parameters);

        return array_column($this->factory->sourceFor($document)->rows($query), 'invoice_no');
    };
});

afterEach(fn () => app(DataSourceRegistry::class)->flush());

it('substitutes a parameter into a filter', function () {
    expect(($this->invoices)(
        [['field' => 'branch', 'operator' => '=', 'value' => '{{ params.branch }}']],
        ['branch' => 'North'],
    ))->toBe(['INV-1', 'INV-3']);
});

it('leaves a filter out when its parameter is empty, rather than matching nothing', function () {
    expect(($this->invoices)(
        [['field' => 'branch', 'operator' => '=', 'value' => '{{ params.branch }}']],
    ))->toBe(['INV-1', 'INV-2', 'INV-3', 'INV-4']);
});

describe('date ranges', function () {
    it('includes every row on the last day of the range', function () {
        expect(($this->invoices)(
            [['field' => 'ordered_at', 'operator' => 'between', 'value' => ['{{ params.from }}', '{{ params.to }}']]],
            ['from' => '2026-09-01', 'to' => '2026-09-30'],
        ))->toBe(['INV-1', 'INV-2', 'INV-3']);
    });

    it('treats a range with only a start as on-or-after', function () {
        expect(($this->invoices)(
            [['field' => 'ordered_at', 'operator' => 'between', 'value' => ['{{ params.from }}', '{{ params.to }}']]],
            ['from' => '2026-09-15'],
        ))->toBe(['INV-2', 'INV-3', 'INV-4']);
    });

    it('treats a range with only an end as on-or-before, including that whole day', function () {
        expect(($this->invoices)(
            [['field' => 'ordered_at', 'operator' => 'between', 'value' => ['{{ params.from }}', '{{ params.to }}']]],
            ['to' => '2026-09-15'],
        ))->toBe(['INV-1', 'INV-2']);
    });

    it('matches the whole day when a date equals a date-time column', function () {
        expect(($this->invoices)(
            [['field' => 'ordered_at', 'operator' => '=', 'value' => '{{ params.from }}']],
            ['from' => '2026-09-15'],
        ))->toBe(['INV-2']);
    });

    it('filters the same whichever way a date was typed', function () {
        expect(($this->invoices)(
            [['field' => 'ordered_at', 'operator' => '=', 'value' => '{{ params.from }}']],
            ['from' => '15 September 2026'],
        ))->toBe(['INV-2']);
    });
});

it('compares a numeric parameter as a number', function () {
    expect(($this->invoices)(
        [['field' => 'total', 'operator' => '>=', 'value' => '{{ params.min_total }}']],
        ['min_total' => '250'],
    ))->toBe(['INV-2', 'INV-4']);
});

it('accepts a parameter inside an "is one of" list', function () {
    expect(($this->invoices)(
        [['field' => 'branch', 'operator' => 'in', 'value' => ['{{ params.branch }}', 'East']]],
        ['branch' => 'South'],
    ))->toBe(['INV-2', 'INV-4']);
});

it('rejects a reference to a parameter the source does not declare, before any values exist', function () {
    $this->factory->validateBindings(($this->document)(
        [['field' => 'branch', 'operator' => '=', 'value' => '{{ params.region }}']],
    ));
})->throws(UnknownParameter::class, 'refers to the parameter [region]');

it('compares text that merely contains a reference literally', function () {
    expect(($this->invoices)(
        [['field' => 'branch', 'operator' => '=', 'value' => 'North {{ params.branch }}']],
        ['branch' => 'North'],
    ))->toBe([]);
});

it('passes parameter values to the database as bindings, never as SQL', function () {
    expect(($this->invoices)(
        [['field' => 'branch', 'operator' => '=', 'value' => '{{ params.branch }}']],
        ['branch' => "North' OR '1'='1"],
    ))->toBe([]);
});

describe('preview parameters', function () {
    it('uses a supplied value and leaves the rest optional', function () {
        $query = $this->factory->preview(
            ($this->document)([['field' => 'branch', 'operator' => '=', 'value' => '{{ params.branch }}']]),
            10,
            ['branch' => 'South', 'from' => ''],
        );

        expect($query->parameters)->toBe(['from' => null, 'to' => null, 'branch' => 'South', 'min_total' => null]);
    });

    it('reports a value of the wrong type', function () {
        $this->factory->preview(($this->document)([]), 10, ['min_total' => 'lots']);
    })->throws(InvalidReportParameters::class);
});
