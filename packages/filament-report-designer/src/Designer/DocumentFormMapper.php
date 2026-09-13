<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Designer;

use ReportBrains\ReportDesigner\DataSources\ParameterReference;
use ReportBrains\ReportDesigner\Schema\BandName;
use ReportBrains\ReportDesigner\Schema\BlockType;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

/**
 * Converts between a report document and the visual designer's form state.
 *
 * The two shapes differ for good reasons on each side: Filament's Builder wraps
 * every block as `{type, data}` keyed by UUID, and a filter value is typed as
 * one line of text; the document stores flat, ordered blocks and real arrays.
 * Keeping the translation in one class means the form never leaks its shape
 * into storage, and the round trip can be tested without rendering a page.
 */
class DocumentFormMapper
{
    /**
     * Operators whose value is a list, typed in the designer as comma-separated text.
     */
    private const LIST_OPERATORS = ['in', 'not_in', 'between'];

    public static function bandKey(BandName $band): string
    {
        return 'band_'.$band->value;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function toFormState(array $document): array
    {
        $data = $document['data'] ?? [];
        $page = $document['page'] ?? [];

        $state = [
            'source' => $data['source'] ?? null,
            'group_by' => $data['group_by'][0] ?? null,
            'sort' => array_map(
                fn (array $sort): array => ['field' => $sort['field'] ?? null, 'dir' => $sort['dir'] ?? 'asc'],
                array_values($data['sort'] ?? []),
            ),
            'filters' => array_map($this->filterToState(...), array_values($data['filters'] ?? [])),
            'page_size' => $page['size'] ?? null,
            'page_orientation' => $page['orientation'] ?? null,
        ];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $state["margin_{$side}"] = $page['margin'][$side] ?? null;
        }

        foreach (BandName::cases() as $band) {
            $state[static::bandKey($band)] = array_map(
                $this->blockToItem(...),
                array_values($document['bands'][$band->value] ?? []),
            );
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state  Designer form state.
     * @param  array<string, mixed>  $identity  `key` and `title`.
     * @return array<string, mixed>
     */
    public function toDocument(array $state, array $identity): array
    {
        $document = [
            'schema_version' => ReportSchema::CURRENT_VERSION,
            'key' => (string) ($identity['key'] ?? ''),
            'title' => (string) ($identity['title'] ?? ''),
        ];

        $document['data'] = $this->data($state);

        if (($page = $this->page($state)) !== []) {
            $document['page'] = $page;
        }

        $document['bands'] = [];

        foreach (BandName::cases() as $band) {
            $blocks = array_values(array_filter(array_map(
                $this->itemToBlock(...),
                array_values($state[static::bandKey($band)] ?? []),
            )));

            if ($blocks !== []) {
                $document['bands'][$band->value] = $blocks;
            }
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(array $state): array
    {
        $data = ['source' => (string) ($state['source'] ?? '')];

        $sort = [];

        foreach (array_values($state['sort'] ?? []) as $entry) {
            if (filled($entry['field'] ?? null)) {
                $sort[] = ['field' => $entry['field'], 'dir' => ($entry['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc'];
            }
        }

        if ($sort !== []) {
            $data['sort'] = $sort;
        }

        if (filled($state['group_by'] ?? null)) {
            $data['group_by'] = [$state['group_by']];
        }

        $filters = array_values(array_filter(array_map(
            $this->filterToDocument(...),
            array_values(array_filter($state['filters'] ?? [], is_array(...))),
        )));

        if ($filters !== []) {
            $data['filters'] = $filters;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function page(array $state): array
    {
        $page = array_filter([
            'size' => $state['page_size'] ?? null,
            'orientation' => $state['page_orientation'] ?? null,
        ], filled(...));

        $margin = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            if (is_numeric($state["margin_{$side}"] ?? null)) {
                $margin[$side] = $state["margin_{$side}"] + 0;
            }
        }

        if ($margin !== []) {
            $page['margin'] = $margin;
        }

        return $page;
    }

    /**
     * A filter as the designer shows it: either a fixed value, or one or two
     * report parameters chosen from a list.
     *
     * A range mixing a fixed bound with a parameter has no dedicated control, so
     * it is shown as text; it still round-trips unchanged.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    private function filterToState(array $filter): array
    {
        $operator = (string) ($filter['operator'] ?? '=');
        $value = $filter['value'] ?? null;

        $state = [
            'field' => $filter['field'] ?? null,
            'operator' => $operator,
            'value_mode' => 'fixed',
            'value' => null,
            'parameter' => null,
            'parameter_to' => null,
        ];

        if ($operator === 'between' && is_array($value)) {
            $bounds = array_values($value) + [null, null];
            $from = ParameterReference::parse($bounds[0]);
            $to = ParameterReference::parse($bounds[1]);

            if (($from !== null || $bounds[0] === null) && ($to !== null || $bounds[1] === null) && ($from ?? $to) !== null) {
                return [...$state, 'value_mode' => 'parameter', 'parameter' => $from, 'parameter_to' => $to];
            }
        }

        if (! is_array($value) && ! in_array($operator, ['in', 'not_in'], strict: true)
            && ($name = ParameterReference::parse($value)) !== null) {
            return [...$state, 'value_mode' => 'parameter', 'parameter' => $name];
        }

        return [...$state, 'value' => is_array($value) ? implode(', ', $value) : $value];
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array{field: string, operator: string, value: mixed}|null
     */
    private function filterToDocument(array $filter): ?array
    {
        if (! filled($filter['field'] ?? null)) {
            return null;
        }

        $operator = (string) ($filter['operator'] ?? '=');
        $usesParameter = ($filter['value_mode'] ?? 'fixed') === 'parameter'
            && ! in_array($operator, ['in', 'not_in'], strict: true);

        if ($usesParameter && $operator === 'between') {
            $bounds = [$this->reference($filter['parameter'] ?? null), $this->reference($filter['parameter_to'] ?? null)];

            return $bounds === [null, null] ? null : ['field' => $filter['field'], 'operator' => $operator, 'value' => $bounds];
        }

        if ($usesParameter) {
            $reference = $this->reference($filter['parameter'] ?? null);

            return $reference === null ? null : ['field' => $filter['field'], 'operator' => $operator, 'value' => $reference];
        }

        $value = $filter['value'] ?? null;

        if (in_array($operator, self::LIST_OPERATORS, strict: true) && is_string($value)) {
            $value = array_values(array_filter(array_map(trim(...), explode(',', $value)), filled(...)));
        }

        return ['field' => $filter['field'], 'operator' => $operator, 'value' => $value];
    }

    private function reference(mixed $parameter): ?string
    {
        return is_string($parameter) && $parameter !== '' ? ParameterReference::to($parameter) : null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    private function blockToItem(array $block): array
    {
        $data = $block;
        unset($data['type']);

        if (isset($data['columns']) && is_array($data['columns'])) {
            $data['columns'] = array_values($data['columns']);
        }

        return ['type' => (string) ($block['type'] ?? ''), 'data' => $data];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemToBlock(mixed $item): ?array
    {
        $type = is_array($item) ? BlockType::tryFrom((string) ($item['type'] ?? '')) : null;

        if ($type === null) {
            return null;
        }

        $data = is_array($item['data'] ?? null) ? $item['data'] : [];

        $block = match ($type) {
            BlockType::Heading => [
                'level' => (int) ($data['level'] ?? 1),
                'content' => (string) ($data['content'] ?? ''),
                'align' => $data['align'] ?? null,
            ],
            BlockType::Text => [
                'content' => (string) ($data['content'] ?? ''),
                'align' => $data['align'] ?? null,
                'bold' => ($data['bold'] ?? false) ? true : null,
                'italic' => ($data['italic'] ?? false) ? true : null,
            ],
            BlockType::Table => [
                'columns' => array_values(array_map(
                    fn (array $column): array => array_filter([
                        'field' => $column['field'] ?? null,
                        'label' => $column['label'] ?? null,
                        'align' => $column['align'] ?? null,
                        'width' => $column['width'] ?? null,
                        'format' => $column['format'] ?? null,
                    ], filled(...)),
                    array_filter(array_values($data['columns'] ?? []), is_array(...)),
                )),
            ],
            BlockType::Divider => [],
            BlockType::Spacer => [
                'height' => is_numeric($data['height'] ?? null) ? (int) $data['height'] : null,
            ],
        };

        // Optional keys left empty in the form are dropped, not stored as null,
        // so a designed document matches one written by hand.
        return ['type' => $type->value, ...array_filter($block, fn (mixed $value): bool => $value !== null && $value !== '')];
    }
}
