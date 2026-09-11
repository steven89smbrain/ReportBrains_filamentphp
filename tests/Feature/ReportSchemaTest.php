<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

beforeEach(function () {
    $this->schema = app(ReportSchema::class);
});

it('accepts a well formed document', function () {
    expect($this->schema->validate(validReportDocument()))->toBeArray();
});

it('rejects a document written against a future schema version', function () {
    $this->schema->validate(validReportDocument(['schema_version' => 99]));
})->throws(InvalidReportSchema::class);

it('rejects a key that is not a slug', function () {
    $this->schema->validate(validReportDocument(['key' => 'Monthly Sales!']));
})->throws(InvalidReportSchema::class);

it('requires a data source', function () {
    $document = validReportDocument();
    unset($document['data']['source']);

    $this->schema->validate($document);
})->throws(InvalidReportSchema::class);

it('names the offending band when it is not recognised', function () {
    $document = validReportDocument();
    $document['bands']['sidebar'] = [];

    expect(fn () => $this->schema->validate($document))
        ->toThrow(function (InvalidReportSchema $exception) {
            expect($exception->errors)->toHaveKey('bands.sidebar')
                ->and($exception->summary())->toContain('Unknown band "sidebar"');
        });
});

it('names the offending block when its type is not recognised', function () {
    $document = validReportDocument();
    $document['bands']['detail'][] = ['type' => 'barcode'];

    expect(fn () => $this->schema->validate($document))
        ->toThrow(function (InvalidReportSchema $exception) {
            expect($exception->errors)->toHaveKey('bands.detail.1.type')
                ->and($exception->summary())->toContain('Unknown block type "barcode"');
        });
});

it('validates each block against the rules for its own type', function () {
    $document = validReportDocument();
    $document['bands']['detail'] = [['type' => 'table', 'columns' => []]];

    expect(fn () => $this->schema->validate($document))
        ->toThrow(function (InvalidReportSchema $exception) {
            expect($exception->errors)->toHaveKey('bands.detail.0.columns');
        });
});

it('rejects a heading level outside 1 to 6', function () {
    $document = validReportDocument();
    $document['bands']['document_header'] = [['type' => 'heading', 'level' => 9, 'content' => 'x']];

    $this->schema->validate($document);
})->throws(InvalidReportSchema::class);

it('reports every failure at once rather than stopping at the first', function () {
    $document = validReportDocument();
    $document['bands']['sidebar'] = [];
    $document['bands']['footer_extra'] = [];

    expect(fn () => $this->schema->validate($document))
        ->toThrow(fn (InvalidReportSchema $e) => expect($e->errors)->toHaveCount(2));
});
