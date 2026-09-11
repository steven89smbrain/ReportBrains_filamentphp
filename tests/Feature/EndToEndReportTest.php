<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Facades\ReportData;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use Tests\Fixtures\Customer;
use Tests\Fixtures\Order;

/**
 * Walks the whole path a report takes: a template stored in the database, bound
 * to a registered source, compiled against real rows and rendered to Markdown.
 */
beforeEach(function () {
    createOrderTables();

    $acme = Customer::create(['name' => 'Acme', 'city' => 'Jakarta']);
    $globex = Customer::create(['name' => 'Globex', 'city' => 'Bandung']);

    Order::create(['customer_id' => $acme->id, 'invoice_no' => 'INV-1', 'total' => 100, 'branch' => 'North', 'secret_note' => 'hidden']);
    Order::create(['customer_id' => $globex->id, 'invoice_no' => 'INV-2', 'total' => 250, 'branch' => 'South', 'secret_note' => 'hidden']);
    Order::create(['customer_id' => $acme->id, 'invoice_no' => 'INV-3', 'total' => 50, 'branch' => 'North', 'secret_note' => 'hidden']);

    ReportData::eloquent('orders', Order::class, function (EloquentSource $source): void {
        $source->setLabel('Orders')
            ->addField('invoice_no', 'Invoice number')
            ->addField('customer.name', 'Customer name')
            ->addField('total', 'Total', FieldType::Currency)
            ->addField('branch', 'Branch')
            ->addParameter('min_total', 'Minimum total', FieldType::Number);
    });

    $this->document = [
        'schema_version' => 1,
        'key' => 'orders-by-branch',
        'title' => 'Orders by Branch',
        'params' => [['name' => 'min_total', 'type' => 'number', 'label' => 'Minimum total']],
        'data' => [
            'source' => 'orders',
            'sort' => [['field' => 'invoice_no', 'dir' => 'asc']],
            'group_by' => ['branch'],
        ],
        'bands' => [
            'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'Orders by Branch']],
            'group_header' => [['type' => 'heading', 'level' => 2, 'content' => 'Branch: {{ group.value }}']],
            'detail' => [[
                'type' => 'table',
                'columns' => [
                    ['field' => 'invoice_no', 'label' => 'Invoice'],
                    ['field' => 'customer.name', 'label' => 'Customer'],
                    ['field' => 'total', 'label' => 'Total', 'align' => 'right', 'format' => 'currency'],
                ],
            ]],
            'group_footer' => [['type' => 'text', 'content' => 'Subtotal: {{ sum(total) | currency }}', 'align' => 'right']],
            'document_footer' => [['type' => 'text', 'content' => 'Grand total: {{ sum(total) | currency }}', 'bold' => true]],
        ],
    ];

    $this->run = function (array $parameters = []): string {
        $document = ReportTemplate::create([
            'key' => 'orders-by-branch',
            'title' => 'Orders by Branch',
            'schema' => $this->document,
        ])->schema;

        $query = app(ReportQueryFactory::class)->make($document, $parameters);
        $rows = app(ReportQueryFactory::class)->sourceFor($document)->rows($query);
        $report = app(ReportCompiler::class)->compile($document, $rows, $query->parameters);

        return (new MarkdownRenderer)->render($report);
    };
});

afterEach(fn () => app(DataSourceRegistry::class)->flush());

it('renders a grouped report from a stored template', function () {
    expect(($this->run)())->toBe(<<<'MD'
    # Orders by Branch

    ## Branch: North

    | Invoice | Customer | Total |
    | --- | --- | ---: |
    | INV-1 | Acme | $100.00 |
    | INV-3 | Acme | $50.00 |

    Subtotal: $150.00

    ## Branch: South

    | Invoice | Customer | Total |
    | --- | --- | ---: |
    | INV-2 | Globex | $250.00 |

    Subtotal: $250.00

    **Grand total: $400.00**

    MD);
});

it('reads values across a relation', function () {
    expect(($this->run)())->toContain('| INV-2 | Globex |');
});

it('never leaks a column the source did not expose', function () {
    expect(($this->run)())->not->toContain('hidden');
});

it('formats currency according to configuration', function () {
    config()->set('report-designer.formatting.currency_symbol', 'Rp ');
    config()->set('report-designer.formatting.decimal_separator', ',');
    config()->set('report-designer.formatting.thousands_separator', '.');
    config()->set('report-designer.formatting.currency_decimals', 0);

    expect(($this->run)())->toContain('**Grand total: Rp 400**');
});
