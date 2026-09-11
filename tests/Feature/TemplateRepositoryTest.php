<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\TemplateNotFound;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\TemplateRepository;

beforeEach(function () {
    $this->directory = storage_path('framework/testing/reports');
    File::ensureDirectoryExists($this->directory);
    config()->set('report-designer.file_templates.path', $this->directory);

    $this->repository = app(TemplateRepository::class);
});

afterEach(function () {
    File::deleteDirectory($this->directory);
});

it('finds a stored template by key', function () {
    ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    expect($this->repository->find('user-directory')->title)->toBe('User Directory');
});

it('throws when the key is not stored', function () {
    $this->repository->find('does-not-exist');
})->throws(TemplateNotFound::class);

it('returns null instead of throwing when asked for a missing key leniently', function () {
    expect($this->repository->findOrNull('does-not-exist'))->toBeNull();
});

it('reads a template from a JSON file', function () {
    File::put($this->directory.'/user-directory.json', json_encode(validReportDocument()));

    expect($this->repository->fromFileKey('user-directory'))
        ->toHaveKey('title', 'User Directory');
});

it('validates a file template rather than trusting the file', function () {
    File::put(
        $this->directory.'/broken.json',
        json_encode(validReportDocument(['bands' => ['nonsense' => []]])),
    );

    $this->repository->fromFileKey('broken');
})->throws(InvalidReportSchema::class);

it('rejects a file that is not valid JSON', function () {
    File::put($this->directory.'/malformed.json', '{"title": ');

    $this->repository->fromFileKey('malformed');
})->throws(InvalidReportSchema::class);

it('refuses to read a file outside the template directory', function () {
    // A traversal that resolves to a real, readable file: without the guard this
    // would hand the caller the application's environment file.
    $this->repository->fromFile($this->directory.'/../../../../.env');
})->throws(TemplateNotFound::class, 'outside the configured template directory');

it('refuses an absolute path outside the template directory', function () {
    $this->repository->fromFile(base_path('composer.json'));
})->throws(TemplateNotFound::class, 'outside the configured template directory');
