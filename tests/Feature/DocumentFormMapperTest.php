<?php

declare(strict_types=1);

use ReportBrains\ReportDesigner\Designer\DocumentFormMapper;
use ReportBrains\ReportDesigner\Schema\BandName;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

beforeEach(function () {
    $this->mapper = app(DocumentFormMapper::class);

    $this->document = [
        'schema_version' => 1,
        'key' => 'orders',
        'title' => 'Orders',
        'data' => [
            'source' => 'orders',
            'sort' => [['field' => 'total', 'dir' => 'desc']],
            'group_by' => ['branch'],
            'filters' => [
                ['field' => 'total', 'operator' => '>', 'value' => '100'],
                ['field' => 'branch', 'operator' => 'in', 'value' => ['North', 'South']],
            ],
        ],
        'page' => ['size' => 'A4', 'orientation' => 'landscape', 'margin' => ['top' => 20, 'left' => 15]],
        'bands' => [
            'document_header' => [['type' => 'heading', 'level' => 1, 'content' => 'Orders']],
            'detail' => [[
                'type' => 'table',
                'columns' => [
                    ['field' => 'invoice_no', 'label' => 'Invoice'],
                    ['field' => 'total', 'label' => 'Total', 'align' => 'right', 'format' => 'currency'],
                ],
            ]],
            'group_footer' => [['type' => 'text', 'content' => '{{ sum(total) }}', 'bold' => true]],
            'document_footer' => [['type' => 'divider'], ['type' => 'spacer', 'height' => 24]],
        ],
    ];
});

it('survives a round trip through the designer unchanged', function () {
    $state = $this->mapper->toFormState($this->document);

    expect($this->mapper->toDocument($state, $this->document))->toBe($this->document);
});

it('wraps blocks the way Filament\'s builder expects', function () {
    $state = $this->mapper->toFormState($this->document);

    expect($state[DocumentFormMapper::bandKey(BandName::DocumentHeader)])
        ->toBe([['type' => 'heading', 'data' => ['level' => 1, 'content' => 'Orders']]]);
});

it('shows a list filter value as comma separated text and reads it back as a list', function () {
    $state = $this->mapper->toFormState($this->document);

    expect($state['filters'][1]['value'])->toBe('North, South');

    $state['filters'][1]['value'] = ' East ,West,, ';

    expect($this->mapper->toDocument($state, $this->document)['data']['filters'][1]['value'])
        ->toBe(['East', 'West']);
});

it('ignores Filament item keys and keeps the order the user arranged', function () {
    $state = $this->mapper->toFormState($this->document);
    $key = DocumentFormMapper::bandKey(BandName::DocumentFooter);

    // Builder state is keyed by UUID; the user dragged the spacer above the divider.
    $state[$key] = [
        'b7c1' => ['type' => 'spacer', 'data' => ['height' => '24']],
        'a3f9' => ['type' => 'divider', 'data' => []],
    ];

    expect($this->mapper->toDocument($state, $this->document)['bands']['document_footer'])
        ->toBe([['type' => 'spacer', 'height' => 24], ['type' => 'divider']]);
});

it('drops optional values left empty instead of storing nulls', function () {
    $state = [
        'source' => 'orders',
        DocumentFormMapper::bandKey(BandName::Detail) => [
            ['type' => 'text', 'data' => ['content' => 'Hi', 'align' => null, 'bold' => false, 'italic' => false]],
        ],
    ];

    expect($this->mapper->toDocument($state, ['key' => 'x', 'title' => 'X'])['bands']['detail'])
        ->toBe([['type' => 'text', 'content' => 'Hi']]);
});

it('omits empty bands, sort, grouping, filters and page setup', function () {
    $document = $this->mapper->toDocument(['source' => 'orders'], ['key' => 'x', 'title' => 'X']);

    expect($document)->toBe([
        'schema_version' => ReportSchema::CURRENT_VERSION,
        'key' => 'x',
        'title' => 'X',
        'data' => ['source' => 'orders'],
        'bands' => [],
    ]);
});

it('skips half-filled rows the user has not finished', function () {
    $document = $this->mapper->toDocument([
        'source' => 'orders',
        'sort' => [['field' => null, 'dir' => 'asc']],
        'filters' => [['field' => '', 'operator' => '=', 'value' => 'x']],
    ], ['key' => 'x', 'title' => 'X']);

    expect($document['data'])->toBe(['source' => 'orders']);
});

describe('filters compared with parameters', function () {
    beforeEach(function () {
        $this->withFilter = fn (array $filter): array => [
            ...$this->document,
            'data' => ['source' => 'orders', 'filters' => [$filter]],
        ];
    });

    it('shows a filter bound to a parameter as a parameter choice', function () {
        $state = $this->mapper->toFormState(($this->withFilter)(
            ['field' => 'branch', 'operator' => '=', 'value' => '{{ params.branch }}'],
        ));

        expect($state['filters'][0])->toMatchArray(['value_mode' => 'parameter', 'parameter' => 'branch', 'value' => null]);
    });

    it('round-trips a date range bound to two parameters', function () {
        $document = ($this->withFilter)(
            ['field' => 'ordered_at', 'operator' => 'between', 'value' => ['{{ params.from }}', '{{ params.to }}']],
        );
        $state = $this->mapper->toFormState($document);

        expect($state['filters'][0])->toMatchArray(['value_mode' => 'parameter', 'parameter' => 'from', 'parameter_to' => 'to'])
            ->and($this->mapper->toDocument($state, $document))->toBe($document);
    });

    it('round-trips a range with only one bound chosen', function () {
        $document = ($this->withFilter)(
            ['field' => 'ordered_at', 'operator' => 'between', 'value' => ['{{ params.from }}', null]],
        );

        expect($this->mapper->toDocument($this->mapper->toFormState($document), $document))->toBe($document);
    });

    it('shows a range mixing a fixed bound and a parameter as text, and round-trips it', function () {
        $document = ($this->withFilter)(
            ['field' => 'ordered_at', 'operator' => 'between', 'value' => ['2026-01-01', '{{ params.to }}']],
        );
        $state = $this->mapper->toFormState($document);

        expect($state['filters'][0])->toMatchArray(['value_mode' => 'fixed', 'value' => '2026-01-01, {{ params.to }}'])
            ->and($this->mapper->toDocument($state, $document))->toBe($document);
    });

    it('writes a parameter choice back as a reference', function () {
        $document = $this->mapper->toDocument([
            'source' => 'orders',
            'filters' => [['field' => 'total', 'operator' => '>=', 'value_mode' => 'parameter', 'parameter' => 'min_total']],
        ], ['key' => 'x', 'title' => 'X']);

        expect($document['data']['filters'])->toBe([['field' => 'total', 'operator' => '>=', 'value' => '{{ params.min_total }}']]);
    });

    it('ignores a parameter choice left behind on "is one of"', function () {
        $document = $this->mapper->toDocument([
            'source' => 'orders',
            'filters' => [['field' => 'branch', 'operator' => 'in', 'value_mode' => 'parameter', 'parameter' => 'branch', 'value' => 'North, South']],
        ], ['key' => 'x', 'title' => 'X']);

        expect($document['data']['filters'][0]['value'])->toBe(['North', 'South']);
    });

    it('skips a parameter filter whose parameter has not been chosen yet', function () {
        $document = $this->mapper->toDocument([
            'source' => 'orders',
            'filters' => [['field' => 'total', 'operator' => '>=', 'value_mode' => 'parameter', 'parameter' => null]],
        ], ['key' => 'x', 'title' => 'X']);

        expect($document['data'])->toBe(['source' => 'orders']);
    });
});
