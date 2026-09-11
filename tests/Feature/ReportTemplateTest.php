<?php

declare(strict_types=1);

use App\Models\User;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Models\ReportTemplate;

it('stores a document and reads it back as an array', function () {
    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    expect($template->fresh()->schema)
        ->toBeArray()
        ->and($template->fresh()->schema['bands']['detail'][0]['type'])->toBe('table');
});

it('mirrors the key and title columns into the stored document', function () {
    $template = ReportTemplate::create([
        'key' => 'quarterly-sales',
        'title' => 'Quarterly Sales',
        // The document still carries the values from the fixture.
        'schema' => validReportDocument(),
    ]);

    expect($template->schema['key'])->toBe('quarterly-sales')
        ->and($template->schema['title'])->toBe('Quarterly Sales');
});

it('defaults the schema version when the document omits it', function () {
    $document = validReportDocument();
    unset($document['schema_version']);

    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => $document,
    ]);

    expect($template->schema_version)->toBe(1);
});

it('refuses to store an invalid document', function () {
    ReportTemplate::create([
        'key' => 'broken',
        'title' => 'Broken',
        'schema' => validReportDocument(['bands' => ['nonsense' => []]]),
    ]);
})->throws(InvalidReportSchema::class);

it('refuses to store an invalid document on update too', function () {
    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    $document = $template->schema;
    $document['data'] = [];

    $template->update(['schema' => $document]);
})->throws(InvalidReportSchema::class);

it('leaves templates unscoped while ownership and tenancy are disabled', function () {
    ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    expect(ReportTemplate::query()->visible()->count())->toBe(1);
});

it('records the owner when ownership is enabled', function () {
    config()->set('report-designer.ownership.enabled', true);

    $user = User::factory()->create();
    $this->actingAs($user);

    $template = ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    expect($template->owner_id)->toBe($user->getKey())
        ->and(ReportTemplate::query()->visible()->count())->toBe(1);
});

it('hides templates belonging to another owner', function () {
    config()->set('report-designer.ownership.enabled', true);

    $this->actingAs(User::factory()->create());
    ReportTemplate::create([
        'key' => 'user-directory',
        'title' => 'User Directory',
        'schema' => validReportDocument(),
    ]);

    $this->actingAs(User::factory()->create());

    expect(ReportTemplate::query()->visible()->count())->toBe(0);
});
