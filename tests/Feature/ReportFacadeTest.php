<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\Events\ReportRendered;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFormat;
use ReportBrains\ReportDesigner\Facades\Report;
use ReportBrains\ReportDesigner\Facades\ReportData;
use ReportBrains\ReportDesigner\Jobs\RenderReport;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Output\OutputFormats;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\ReportRunner;

beforeEach(function () {
    $this->ada = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $this->grace = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    ReportTemplate::create(['key' => 'user-directory', 'title' => 'User Directory', 'schema' => validReportDocument()]);

    // A source that only ever shows the signed-in user, to prove scopes hold
    // wherever a report runs.
    ReportData::eloquent('me', User::class, function (EloquentSource $source): void {
        $source->setLabel('Me')
            ->addField('email', 'Email address')
            ->scope(fn ($query) => $query->whereKey(Auth::id()));
    });

    $this->onlyMe = [
        'schema_version' => 1,
        'key' => 'only-me',
        'title' => 'Only Me',
        'data' => ['source' => 'me'],
        'bands' => ['detail' => [['type' => 'table', 'columns' => [['field' => 'email', 'label' => 'Email']]]]],
    ];
});

describe('loading a report', function () {
    it('runs a stored template', function () {
        expect(Report::template('user-directory')->toMarkdown())
            ->toStartWith('# User Directory')
            ->toContain('ada@example.com');
    });

    it('runs a file template', function () {
        expect(Report::file('user-directory')->toMarkdown())->toStartWith('# User Directory');
    });

    it('validates a document built in code before running it', function () {
        Report::document(['key' => 'broken', 'title' => 'Broken']);
    })->throws(InvalidReportSchema::class);

    it('leaves the original untouched when parameters are added', function () {
        $report = Report::template('user-directory');
        $withParameters = $report->with(['registered_from' => '2026-01-01']);

        expect($report->parameters())->toBe([])
            ->and($withParameters->parameters())->toBe(['registered_from' => '2026-01-01']);
    });
});

describe('output', function () {
    it('renders every built-in format', function (string $format, string $expected) {
        expect(Report::template('user-directory')->render($format))->toContain($expected);
    })->with([
        ['html', '<td>ada@example.com</td>'],
        ['markdown', '| Ada Lovelace | ada@example.com |'],
        ['csv', '"Ada Lovelace",ada@example.com'],
    ]);

    it('stores a report, choosing the format from the extension', function () {
        Storage::fake();

        $path = Report::template('user-directory')->save('exports/users.md');

        expect($path)->toBe('exports/users.md');
        expect(Storage::get('exports/users.md'))->toStartWith('# User Directory');
    });

    it('stores in the format asked for, whatever the extension', function () {
        Storage::fake('reports');

        Report::template('user-directory')->save('users.txt', disk: 'reports', format: 'csv');

        expect(Storage::disk('reports')->get('users.txt'))->toContain('Name,"Email address"');
    });

    it('streams a download with a dated filename', function () {
        $response = Report::template('user-directory')->download(format: 'markdown');

        expect($response->headers->get('Content-Type'))->toBe('text/markdown')
            ->and($response->headers->get('Content-Disposition'))->toContain('user-directory-'.now()->format('Y-m-d').'.md');
    });

    it('refuses a format that is not registered', function () {
        Report::template('user-directory')->render('docx');
    })->throws(UnsupportedFormat::class, 'Available formats: pdf, html, markdown, csv, xlsx');

    it('accepts formats registered by the application', function () {
        app(OutputFormats::class)->register('txt', 'Plain text', fn (): Renderer => new class implements Renderer
        {
            public function render(RenderedReport $report): string
            {
                return strtoupper($report->title);
            }

            public function extension(): string
            {
                return 'txt';
            }

            public function mimeType(): string
            {
                return 'text/plain';
            }
        });

        expect(Report::template('user-directory')->render('txt'))->toBe('USER DIRECTORY')
            ->and(app(OutputFormats::class)->forExtension('txt'))->toBe('txt');
    });
});

describe('queueing', function () {
    it('queues the report to run as the signed-in user', function () {
        Queue::fake();
        $this->actingAs($this->ada);

        Report::template('user-directory')->with(['registered_from' => '2026-01-01'])->queue('exports/users.csv');

        Queue::assertPushed(RenderReport::class, fn (RenderReport $job): bool => $job->userId === $this->ada->id
            && $job->format === 'csv'
            && $job->path === 'exports/users.csv'
            && $job->parameters === ['registered_from' => '2026-01-01']
            && $job->document['key'] === 'user-directory');
    });

    it('runs as the user who asked, so scopes still apply on the queue', function () {
        Storage::fake();
        Event::fake([ReportRendered::class]);

        // Nobody is signed in on a queue worker.
        Auth::logout();

        (new RenderReport($this->onlyMe, [], 'markdown', 'exports/me.md', null, $this->grace->id, 'web'))
            ->handle(app(ReportRunner::class), app(OutputFormats::class), app('auth'));

        expect(Storage::get('exports/me.md'))->toContain('grace@example.com')->not->toContain('ada@example.com');

        Event::assertDispatched(ReportRendered::class, fn (ReportRendered $event): bool => $event->path === 'exports/me.md'
            && $event->templateKey === 'only-me'
            && $event->userId === $this->grace->id);
    });

    it('refuses to run when the user who asked no longer exists', function () {
        Storage::fake();

        expect(fn () => (new RenderReport($this->onlyMe, [], 'markdown', 'exports/me.md', null, 999_999, 'web'))
            ->handle(app(ReportRunner::class), app(OutputFormats::class), app('auth')))
            ->toThrow(RuntimeException::class, 'no longer exists');

        Storage::assertMissing('exports/me.md');
    });
});
