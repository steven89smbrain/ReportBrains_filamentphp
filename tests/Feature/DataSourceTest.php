<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\DataSources\ReportQuery;
use ReportBrains\ReportDesigner\Exceptions\UnknownDataSource;
use ReportBrains\ReportDesigner\Exceptions\UnknownField;
use ReportBrains\ReportDesigner\Facades\ReportData;
use Tests\Fixtures\Customer;
use Tests\Fixtures\Order;

beforeEach(function () {
    createOrderTables();

    $this->customer = Customer::create(['name' => 'Acme', 'city' => 'Jakarta']);

    Order::create(['customer_id' => $this->customer->id, 'invoice_no' => 'INV-1', 'total' => 100, 'branch' => 'North', 'secret_note' => 'confidential']);
    Order::create(['customer_id' => $this->customer->id, 'invoice_no' => 'INV-2', 'total' => 250, 'branch' => 'South', 'secret_note' => 'confidential']);

    // `secret_note` is deliberately not exposed.
    $this->source = ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('total', 'Total', FieldType::Currency)
            ->addField('branch', 'Branch')
            ->addField('customer.name', 'Customer name')
            ->addParameter('min_total', 'Minimum total', FieldType::Number);
    });
});

afterEach(fn () => app(DataSourceRegistry::class)->flush());

describe('registry', function () {
    it('resolves a registered source', function () {
        expect(ReportData::get('orders')->label())->toBe('Orders');
    });

    it('rejects a source that was never registered', function () {
        ReportData::get('payroll');
    })->throws(UnknownDataSource::class, 'No data source is registered under [payroll]');

    it('lists sources as labelled options for the designer', function () {
        expect(ReportData::options())->toHaveKey('orders', 'Orders');
    });
});

describe('field whitelist', function () {
    it('exposes only the declared fields', function () {
        expect(array_keys($this->source->fields()))
            ->toBe(['invoice_no', 'total', 'branch', 'customer.name'])
            ->not->toContain('secret_note');
    });

    it('carries a human label for every field', function () {
        foreach ($this->source->fields() as $field) {
            expect($field->label)->not->toBe('')->and($field->label)->not->toBe($field->name);
        }
    });

    it('recognises a relation field and its path', function () {
        expect($this->source->field('customer.name')->isRelation())->toBeTrue()
            ->and($this->source->field('customer.name')->relationPath())->toBe('customer')
            ->and($this->source->field('total')->isRelation())->toBeFalse();
    });
});

describe('reading rows', function () {
    it('returns only the requested fields', function () {
        $rows = $this->source->rows(new ReportQuery(fields: ['invoice_no', 'total']));

        expect($rows)->toHaveCount(2)
            ->and(array_keys($rows[0]))->toBe(['invoice_no', 'total']);
    });

    it('reads a field across a relation', function () {
        $rows = $this->source->rows(new ReportQuery(fields: ['invoice_no', 'customer.name']));

        expect($rows[0]['customer.name'])->toBe('Acme');
    });

    it('never returns a column the source did not expose', function () {
        $rows = $this->source->rows(new ReportQuery(fields: ['invoice_no', 'total']));

        foreach ($rows as $row) {
            expect($row)->not->toHaveKey('secret_note');
        }
    });

    it('sorts by a whitelisted column', function () {
        $rows = $this->source->rows(new ReportQuery(
            fields: ['invoice_no'],
            sort: [['field' => 'total', 'direction' => 'desc']],
        ));

        expect(array_column($rows, 'invoice_no'))->toBe(['INV-2', 'INV-1']);
    });

    it('filters with a whitelisted operator', function () {
        $rows = $this->source->rows(new ReportQuery(
            fields: ['invoice_no'],
            filters: [['field' => 'total', 'operator' => '>', 'value' => 200]],
        ));

        expect($rows)->toHaveCount(1)->and($rows[0]['invoice_no'])->toBe('INV-2');
    });

    it('rejects a filter on a field the source never exposed', function () {
        $this->source->rows(new ReportQuery(
            fields: ['invoice_no'],
            filters: [['field' => 'secret_note', 'operator' => '=', 'value' => 'confidential']],
        ));
    })->throws(UnknownField::class, 'does not expose a field named [secret_note]');

    it('rejects sorting by a field the source never exposed', function () {
        $this->source->rows(new ReportQuery(
            fields: ['invoice_no'],
            sort: [['field' => 'secret_note', 'direction' => 'asc']],
        ));
    })->throws(UnknownField::class);
});

describe('scopes', function () {
    it('applies a scope to every read', function () {
        $scoped = ReportData::eloquent('north_orders', Order::class, function (EloquentSource $source): void {
            $source->setLabel('North orders')
                ->addField('invoice_no', 'Invoice number')
                ->scope(fn (Builder $query) => $query->where('branch', 'North'));
        });

        $rows = $scoped->rows(new ReportQuery(fields: ['invoice_no']));

        expect($rows)->toHaveCount(1)->and($rows[0]['invoice_no'])->toBe('INV-1');
    });

    it('keeps the scope in force even when a report filters on the same column', function () {
        $scoped = ReportData::eloquent('north_orders', Order::class, function (EloquentSource $source): void {
            $source->setLabel('North orders')
                ->addField('invoice_no', 'Invoice number')
                ->addField('branch', 'Branch')
                ->scope(fn (Builder $query) => $query->where('branch', 'North'));
        });

        // A template trying to reach the other branch gets nothing back.
        $rows = $scoped->rows(new ReportQuery(
            fields: ['invoice_no'],
            filters: [['field' => 'branch', 'operator' => '=', 'value' => 'South']],
        ));

        expect($rows)->toBeEmpty();
    });
});

describe('row caps', function () {
    it('caps rows at the source limit', function () {
        $capped = ReportData::eloquent('capped', Order::class, function (EloquentSource $source): void {
            $source->setLabel('Capped')->addField('invoice_no', 'Invoice number')->maxRows(1);
        });

        expect($capped->rows(new ReportQuery(fields: ['invoice_no'])))->toHaveCount(1);
    });

    it('does not let a query raise the cap', function () {
        $capped = ReportData::eloquent('capped', Order::class, function (EloquentSource $source): void {
            $source->setLabel('Capped')->addField('invoice_no', 'Invoice number')->maxRows(1);
        });

        $query = (new ReportQuery(fields: ['invoice_no']))->withLimit(500);

        expect($capped->rows($query))->toHaveCount(1);
    });
});
