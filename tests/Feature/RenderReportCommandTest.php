<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\Facades\ReportData;
use ReportBrains\ReportDesigner\Jobs\RenderReport;
use ReportBrains\ReportDesigner\Models\ReportTemplate;

beforeEach(function () {
    Storage::fake();

    $this->ada = User::factory()->create(['email' => 'ada@example.com', 'created_at' => '2026-01-05 10:00:00']);
    $this->grace = User::factory()->create(['email' => 'grace@example.com', 'created_at' => '2026-09-01 10:00:00']);

    ReportTemplate::create(['key' => 'user-directory', 'title' => 'User Directory', 'schema' => validReportDocument()]);
});

it('renders a stored template to the given path', function () {
    $this->artisan('report:render', ['template' => 'user-directory', '--output' => 'exports/users.md'])
        ->expectsOutputToContain('exports/users.md')
        ->assertSuccessful();

    expect(Storage::get('exports/users.md'))->toStartWith('# User Directory');
});

it('names the file after the template when no path is given', function () {
    $this->artisan('report:render', ['template' => 'user-directory', '--format' => 'csv'])->assertSuccessful();

    expect(Storage::files('reports'))->toHaveCount(1)
        ->and(Storage::files('reports')[0])->toStartWith('reports/user-directory-')->toEndWith('.csv');
});

it('falls back to a file template', function () {
    $this->artisan('report:render', ['template' => 'user-directory', '--output' => 'exports/file.md'])->assertSuccessful();

    ReportTemplate::query()->delete();

    $this->artisan('report:render', ['template' => 'user-directory', '--output' => 'exports/file.md'])->assertSuccessful();

    expect(Storage::get('exports/file.md'))->toStartWith('# User Directory');
});

it('passes parameters to the report', function () {
    $document = validReportDocument();
    $document['data']['filters'] = [['field' => 'created_at', 'operator' => '>=', 'value' => '{{ params.registered_from }}']];
    ReportTemplate::query()->where('key', 'user-directory')->first()->update(['schema' => $document]);

    $this->artisan('report:render', [
        'template' => 'user-directory',
        '--output' => 'exports/recent.md',
        '--param' => ['registered_from=2026-08-01'],
    ])->assertSuccessful();

    expect(Storage::get('exports/recent.md'))->toContain('grace@example.com')->not->toContain('ada@example.com');
});

it('runs as a given user, so scopes apply', function () {
    ReportData::eloquent('me', User::class, function (EloquentSource $source): void {
        $source->setLabel('Me')->addField('email', 'Email')->scope(fn ($query) => $query->whereKey(Auth::id()));
    });

    ReportTemplate::create(['key' => 'only-me', 'title' => 'Only Me', 'schema' => [
        'schema_version' => 1,
        'data' => ['source' => 'me'],
        'bands' => ['detail' => [['type' => 'table', 'columns' => [['field' => 'email', 'label' => 'Email']]]]],
    ]]);

    $this->artisan('report:render', ['template' => 'only-me', '--output' => 'exports/me.md', '--as' => $this->ada->id])
        ->assertSuccessful();

    expect(Storage::get('exports/me.md'))->toContain('ada@example.com')->not->toContain('grace@example.com');
});

it('fails clearly for a user that does not exist', function () {
    $this->artisan('report:render', ['template' => 'user-directory', '--as' => 999_999])
        ->expectsOutputToContain('No user with ID [999999]')
        ->assertFailed();
});

it('fails clearly for an unknown template', function () {
    $this->artisan('report:render', ['template' => 'nope'])
        ->expectsOutputToContain('nope')
        ->assertFailed();
});

it('fails clearly for an unknown format', function () {
    $this->artisan('report:render', ['template' => 'user-directory', '--format' => 'docx'])
        ->expectsOutputToContain('Unknown format [docx]')
        ->assertFailed();
});

it('queues the report instead of rendering it now', function () {
    Queue::fake();

    $this->artisan('report:render', ['template' => 'user-directory', '--output' => 'exports/users.xlsx', '--queue' => true])
        ->expectsOutputToContain('Queued')
        ->assertSuccessful();

    Queue::assertPushed(RenderReport::class, fn (RenderReport $job): bool => $job->format === 'xlsx');
    Storage::assertMissing('exports/users.xlsx');
});
