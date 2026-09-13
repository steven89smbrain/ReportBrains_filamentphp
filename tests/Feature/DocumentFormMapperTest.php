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
        'params' => [['name' => 'from', 'type' => 'date']],
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

    expect($this->mapper->toDocument($state, $this->document, $this->document))->toBe($this->document);
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

it('does not carry parameters over unless asked to', function () {
    $state = $this->mapper->toFormState($this->document);

    expect($this->mapper->toDocument($state, $this->document))->not->toHaveKey('params');
});
