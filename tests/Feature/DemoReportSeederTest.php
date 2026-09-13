<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use Database\Seeders\DemoReportSeeder;
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use ReportBrains\ReportDesigner\TemplateRepository;

beforeEach(function () {
    $this->render = function (string $key, array $parameters = []): string {
        $document = app(TemplateRepository::class)->find($key)->schema;
        $factory = app(ReportQueryFactory::class);
        $query = $factory->make($document, $parameters);
        $rows = $factory->sourceFor($document)->rows($query);

        return (new MarkdownRenderer)->render(app(ReportCompiler::class)->compile($document, $rows, $query->parameters));
    };
});

it('creates sample sales data', function () {
    $this->seed(DemoReportSeeder::class);

    expect(Branch::query()->count())->toBe(4)
        ->and(Customer::query()->count())->toBe(40)
        ->and(Order::query()->count())->toBe(240);
});

it('loads sample templates that render against the sample data', function (string $key, string $heading) {
    $this->seed(DemoReportSeeder::class);

    expect(($this->render)($key))->toStartWith("# {$heading}");
})->with([
    ['sales-by-branch', 'Sales by Branch'],
    ['order-list', 'Order List'],
    ['user-directory', 'User Directory'],
]);

it('totals exactly the paid orders in the default date range', function () {
    $this->seed(DemoReportSeeder::class);

    $expected = Order::query()
        ->where('status', 'paid')
        ->whereBetween('ordered_at', [now()->subDays(30)->startOfDay(), now()->endOfDay()])
        ->sum('total');

    expect(($this->render)('sales-by-branch'))
        ->toContain('**Grand total: $'.number_format((float) $expected, 2).'**');
});

it('narrows the order list to the status asked for', function () {
    $this->seed(DemoReportSeeder::class);

    $markdown = ($this->render)('order-list', ['status' => 'cancelled', 'from' => now()->subDays(90)->toDateString()]);

    expect($markdown)->toContain('## Status: CANCELLED')
        ->not->toContain('## Status: PAID');
});

it('can be run again without duplicating anything', function () {
    $this->seed(DemoReportSeeder::class);
    $this->seed(DemoReportSeeder::class);

    expect(Order::query()->count())->toBe(240)
        ->and(ReportTemplate::query()->count())->toBe(3);
});
