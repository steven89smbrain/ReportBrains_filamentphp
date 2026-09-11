<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Rules\RegisteredDataSource;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists stored templates', function () {
    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    Livewire::test(ListReportTemplates::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$template]);
});

it('creates a template from JSON typed into the editor', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'user-directory',
            'title' => 'User Directory',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ReportTemplate::query()->where('key', 'user-directory')->exists())->toBeTrue();
});

it('stores the document as an array, not a re-encoded string', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'user-directory',
            'title' => 'User Directory',
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
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'user-directory',
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
            'title' => 'User Directory',
            'schema' => json_encode(validReportDocument()),
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('rejects a document whose data source is not registered', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'payroll-report',
            'title' => 'Payroll Report',
            'schema' => json_encode(validReportDocument(['data' => ['source' => 'payroll']])),
        ])
        ->call('create')
        ->assertHasFormErrors(['schema']);

    expect(ReportTemplate::query()->count())->toBe(0);
});

it('names the available sources when the one asked for is missing', function () {
    $messages = [];

    (new RegisteredDataSource)->validate(
        'schema',
        validReportDocument(['data' => ['source' => 'payroll']]),
        function (string $message) use (&$messages): void {
            $messages[] = $message;
        },
    );

    expect($messages)->toBe(['The data source [payroll] is not registered. Available: users.']);
});
