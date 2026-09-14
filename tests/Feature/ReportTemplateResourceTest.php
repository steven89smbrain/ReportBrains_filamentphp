<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;
use ReportBrains\ReportDesigner\Facades\ReportData;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\EditReportTemplate;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages\ListReportTemplates;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Rules\RegisteredDataSource;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $this->actingAs($this->user);

    // Builder and repeater items are keyed by UUID in the browser; numeric keys
    // make the state predictable in tests.
    $this->undoFakes = [Repeater::fake(), Builder::fake()];

    // A document designed entirely through the form, against the "users" source
    // registered in AppServiceProvider.
    $this->designedState = [
        'key' => 'user-directory',
        'title' => 'User Directory',
        'source' => 'users',
        'band_document_header' => [
            ['type' => 'heading', 'data' => ['content' => 'User Directory', 'level' => 1, 'align' => null]],
        ],
        'band_detail' => [
            ['type' => 'table', 'data' => ['columns' => [
                ['field' => 'name', 'label' => 'Name', 'align' => null, 'format' => null],
                ['field' => 'email', 'label' => 'Email address', 'align' => null, 'format' => null],
            ]]],
        ],
    ];
});

afterEach(function () {
    foreach ($this->undoFakes as $undo) {
        $undo();
    }
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

describe('designing a template', function () {
    it('stores a report designed without touching JSON', function () {
        Livewire::test(CreateReportTemplate::class)
            ->fillForm($this->designedState)
            ->call('create')
            ->assertHasNoFormErrors();

        expect(ReportTemplate::query()->firstOrFail()->schema)->toBe([
            'schema_version' => 1,
            'key' => 'user-directory',
            'title' => 'User Directory',
            'data' => ['source' => 'users'],
            'bands' => [
                'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'User Directory']],
                'detail' => [['type' => 'table', 'columns' => [
                    ['field' => 'name', 'label' => 'Name'],
                    ['field' => 'email', 'label' => 'Email address'],
                ]]],
            ],
        ]);
    });

    it('refuses to save a column the source does not expose, and says why', function () {
        $state = $this->designedState;
        $state['band_detail'][0]['data']['columns'][] = ['field' => 'password', 'label' => 'Password'];

        // The column picker only offers exposed fields, so the value is refused
        // on the field itself before the save-time binding check is reached.
        Livewire::test(CreateReportTemplate::class)
            ->fillForm($state)
            ->call('create')
            ->assertHasFormErrors(['band_detail.0.data.columns.2.field']);

        expect(ReportTemplate::query()->count())->toBe(0);
    });

    it('refuses to save an invalid document, and says why', function () {
        $state = $this->designedState;
        $state['band_detail'] = [['type' => 'text', 'data' => ['content' => '']]];

        Livewire::test(CreateReportTemplate::class)
            ->fillForm($state)
            ->call('create')
            ->assertHasFormErrors(['band_detail.0.data.content']);

        expect(ReportTemplate::query()->count())->toBe(0);
    });

    it('rejects a data source that is not registered', function () {
        Livewire::test(CreateReportTemplate::class)
            ->fillForm([...$this->designedState, 'source' => 'payroll'])
            ->call('create')
            ->assertHasFormErrors(['source']);

        expect(ReportTemplate::query()->count())->toBe(0);
    });

    it('rejects a duplicate key', function () {
        ReportTemplate::create([
            'key' => 'user-directory',
            'title' => 'User Directory',
            'schema' => validReportDocument(),
        ]);

        Livewire::test(CreateReportTemplate::class)
            ->fillForm([...$this->designedState, 'title' => 'Another One'])
            ->call('create')
            ->assertHasFormErrors(['key']);
    });

    it('rejects a key that is not a slug', function () {
        Livewire::test(CreateReportTemplate::class)
            ->fillForm([...$this->designedState, 'key' => 'User Directory!'])
            ->call('create')
            ->assertHasFormErrors(['key']);
    });
});

describe('editing a template', function () {
    beforeEach(function () {
        $this->template = ReportTemplate::create([
            'key' => 'user-directory',
            'title' => 'User Directory',
            'schema' => validReportDocument(),
        ]);
    });

    it('opens the stored document in the designer', function () {
        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->assertSchemaStateSet([
                'source' => 'users',
                'band_detail' => [
                    ['type' => 'table', 'data' => ['columns' => [
                        ['field' => 'name', 'label' => 'Name', 'align' => null, 'format' => null],
                        ['field' => 'email', 'label' => 'Email address', 'align' => null, 'format' => null],
                    ]]],
                ],
            ]);
    });

    it('saves a change made in the designer', function () {
        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->fillForm(['group_by' => 'email'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->template->fresh()->schema['data']['group_by'])->toBe(['email']);
    });

    it('saves a filter compared with a report parameter', function () {
        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->fillForm(['filters' => [[
                'field' => 'created_at',
                'operator' => '>=',
                'value_mode' => 'parameter',
                'parameter' => 'registered_from',
                'value' => null,
            ]]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->template->fresh()->schema['data']['filters'])->toBe([
            ['field' => 'created_at', 'operator' => '>=', 'value' => '{{ params.registered_from }}'],
        ]);
    });
});

describe('importing JSON', function () {
    it('replaces the design with an imported document', function () {
        Livewire::test(CreateReportTemplate::class)
            ->callAction('importJson', data: ['json' => json_encode(validReportDocument())])
            ->assertSchemaStateSet([
                'title' => 'User Directory',
                'source' => 'users',
            ]);
    });

    it('reports malformed JSON instead of replacing the design', function () {
        Livewire::test(CreateReportTemplate::class)
            ->fillForm(['source' => 'users'])
            ->callAction('importJson', data: ['json' => '{"title": '])
            ->assertNotified('Import failed')
            ->assertSchemaStateSet(['source' => 'users']);
    });

    it('is hidden when the panel switches the JSON editor off', function () {
        Filament::getPanel('admin')->getPlugin('report-designer')->jsonEditor(false);

        Livewire::test(CreateReportTemplate::class)->assertActionHidden('importJson');
    });
});

it('refuses to save when a stored column is no longer exposed, and says why', function () {
    // A developer removed a field after the template was saved. Nothing in the
    // form offers it any more, so the save-time binding check is the backstop.
    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    ReportData::eloquent('users', User::class, function ($source): void {
        $source->setLabel('Users')->addField('name', 'Name');
    });

    $page = Livewire::test(EditReportTemplate::class, ['record' => $template->getRouteKey()]);

    $page->set('data.band_detail.0.data.columns', [['field' => 'name', 'label' => 'Name']])
        ->set('data.group_by', null);

    // Put the retired field back in state directly, as stale browser state would.
    $page->set('data.sort', [['field' => 'email', 'dir' => 'asc']])
        ->call('save');

    expect($template->fresh()->schema['data']['sort'])->toBe([['field' => 'created_at', 'dir' => 'desc']]);
});

it('previews with the parameter values typed beside the preview', function () {
    Order::factory()->paid()->create(['invoice_no' => 'INV-RECENT', 'ordered_at' => now()->subDays(2)]);
    Order::factory()->paid()->create(['invoice_no' => 'INV-OLDER', 'ordered_at' => now()->subDays(60)]);

    $page = Livewire::test(CreateReportTemplate::class)
        ->fillForm([
            'key' => 'orders',
            'title' => 'Orders',
            'source' => 'orders',
            'filters' => [[
                'field' => 'ordered_at',
                'operator' => 'between',
                'value_mode' => 'parameter',
                'parameter' => 'from',
                'parameter_to' => 'to',
            ]],
            'band_detail' => [
                ['type' => 'table', 'data' => ['columns' => [['field' => 'invoice_no', 'label' => 'Invoice', 'align' => null, 'format' => null]]]],
            ],
        ]);

    // The source defaults "from" to 30 days ago, so the older order is out of range.
    $page->assertSee('Try the report with')
        ->assertSee('INV-RECENT')
        ->assertDontSee('INV-OLDER');

    $page->set('data.preview_parameters.from', now()->subDays(90)->toDateString())
        ->assertSee('INV-OLDER');
});

it('previews the report against live data while designing', function () {
    Livewire::test(CreateReportTemplate::class)
        ->fillForm($this->designedState)
        ->assertSee('ada@example.com');
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

    expect($messages)->toBe(['The data source [payroll] is not registered. Available: users, orders.']);
});

describe('exporting', function () {
    beforeEach(function () {
        $this->template = ReportTemplate::create([
            'key' => 'user-directory',
            'title' => 'User Directory',
            'schema' => validReportDocument(),
        ]);

        $this->filename = fn (string $extension): string => 'user-directory-'.now()->format('Y-m-d').'.'.$extension;
    });

    it('downloads the saved report as Markdown', function () {
        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->callAction('export', data: ['format' => 'markdown'])
            ->assertFileDownloaded(($this->filename)('md'));
    });

    it('downloads the saved report as a PDF', function () {
        Pdf::fake();

        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->callAction('export', data: ['format' => 'pdf'])
            ->assertFileDownloaded(($this->filename)('pdf'));

        Pdf::assertSee('ada@example.com');
    });

    it('explains a report it cannot run instead of failing', function () {
        // The email field was exposed when the template was saved, and since removed.
        ReportData::eloquent('users', User::class, function ($source): void {
            $source->setLabel('Users')->addField('name', 'Name')->addField('created_at', 'Registered at');
        });

        Livewire::test(EditReportTemplate::class, ['record' => $this->template->getRouteKey()])
            ->callAction('export', data: ['format' => 'markdown'])
            ->assertNotified('The report could not be exported')
            ->assertNoFileDownloaded();
    });
});
