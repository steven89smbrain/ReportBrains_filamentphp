<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use ReportBrains\ReportDesigner\Models\ReportTemplate;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists stored templates', function () {
    $template = ReportTemplate::create([
        'key' => 'monthly-sales',
        'title' => 'Monthly Sales',
        'schema' => validReportDocument(),
    ]);

    Livewire::test(ListReportTemplates::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$template]);
});

it('creates a template from JSON typed into the editor', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'monthly-sales',
            'title' => 'Monthly Sales',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ReportTemplate::query()->where('key', 'monthly-sales')->exists())->toBeTrue();
});

it('stores the document as an array, not a re-encoded string', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'monthly-sales',
            'title' => 'Monthly Sales',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create');

    expect(ReportTemplate::query()->firstOrFail()->schema)->toBeArray();
});

it('surfaces schema errors on the form instead of saving', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'broken',
            'title' => 'Broken',
            'schema' => json_encode(validReportDocument(['bands' => ['nonsense' => []]])),
        ])
        ->call('create')
        ->assertHasFormErrors(['schema']);

    expect(ReportTemplate::query()->count())->toBe(0);
});

it('rejects malformed JSON on the form', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'broken',
            'title' => 'Broken',
            'schema' => '{"title": ',
        ])
        ->call('create')
        ->assertHasFormErrors(['schema']);
});

it('rejects a duplicate key', function () {
    ReportTemplate::create([
        'key' => 'monthly-sales',
        'title' => 'Monthly Sales',
        'schema' => validReportDocument(),
    ]);

    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'monthly-sales',
            'title' => 'Another One',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('rejects a key that is not a slug', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'Monthly Sales!',
            'title' => 'Monthly Sales',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});
