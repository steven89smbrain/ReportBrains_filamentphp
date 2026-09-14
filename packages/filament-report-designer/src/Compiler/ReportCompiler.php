<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Compiler;

use ReportBrains\ReportDesigner\Exceptions\UnsupportedFeature;
use ReportBrains\ReportDesigner\Expressions\EvaluationContext;
use ReportBrains\ReportDesigner\Expressions\ExpressionEvaluator;
use ReportBrains\ReportDesigner\Expressions\ValueFormatter;
use ReportBrains\ReportDesigner\Schema\BandName;
use ReportBrains\ReportDesigner\Schema\BlockType;

/**
 * Turns a document plus its rows into a fully resolved RenderedReport.
 *
 * All the report-specific thinking lives here — repeating the detail band,
 * splitting rows into groups, evaluating expressions, computing aggregates —
 * so that renderers are left with nothing but translation to do.
 */
class ReportCompiler
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
        private readonly ValueFormatter $formatter,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $parameters
     */
    public function compile(array $document, array $rows, array $parameters = []): RenderedReport
    {
        $context = new EvaluationContext(rows: $rows, parameters: $parameters);
        $bandDefinitions = $document['bands'] ?? [];
        $groupBy = $document['data']['group_by'] ?? [];

        if (count($groupBy) > 1) {
            throw UnsupportedFeature::nestedGrouping(count($groupBy));
        }

        $bands = [];

        $bands = [
            ...$bands,
            ...$this->staticBand(BandName::DocumentHeader, $bandDefinitions, $context),
            ...$this->staticBand(BandName::PageHeader, $bandDefinitions, $context->withPagination()),
        ];

        $bands = [...$bands, ...($groupBy === []
            ? $this->detailBands($bandDefinitions, $rows, $context)
            : $this->groupedBands($bandDefinitions, $rows, (string) $groupBy[0], $context))];

        $bands = [
            ...$bands,
            ...$this->staticBand(BandName::PageFooter, $bandDefinitions, $context->withPagination()),
            ...$this->staticBand(BandName::DocumentFooter, $bandDefinitions, $context),
        ];

        return new RenderedReport(
            title: (string) ($document['title'] ?? ''),
            bands: $bands,
            page: $document['page'] ?? [],
            rowCount: count($rows),
        );
    }

    /**
     * A band rendered once, with every row in scope for aggregates.
     *
     * @return array<int, RenderedBand>
     */
    private function staticBand(BandName $name, array $definitions, EvaluationContext $context): array
    {
        $blocks = $definitions[$name->value] ?? [];

        if ($blocks === []) {
            return [];
        }

        return [new RenderedBand($name, $this->compileBlocks($blocks, $context->rows, $context, repeatPerRow: false))];
    }

    /**
     * @return array<int, RenderedBand>
     */
    private function detailBands(array $definitions, array $rows, EvaluationContext $context): array
    {
        $blocks = $definitions[BandName::Detail->value] ?? [];

        if ($blocks === []) {
            return [];
        }

        return [new RenderedBand(BandName::Detail, $this->compileBlocks($blocks, $rows, $context, repeatPerRow: true))];
    }

    /**
     * Split rows by the grouping field, wrapping each group in its own bands.
     *
     * @return array<int, RenderedBand>
     */
    private function groupedBands(array $definitions, array $rows, string $field, EvaluationContext $context): array
    {
        $bands = [];

        foreach ($this->partition($rows, $field) as $value => $groupRows) {
            $groupContext = $context
                ->withRows($groupRows)
                ->withGroup(['field' => $field, 'value' => $value]);

            $bands = [
                ...$bands,
                ...$this->staticBand(BandName::GroupHeader, $definitions, $groupContext),
                ...$this->detailBands($definitions, $groupRows, $groupContext),
                ...$this->staticBand(BandName::GroupFooter, $definitions, $groupContext),
            ];
        }

        return $bands;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function partition(array $rows, string $field): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) ($row[$field] ?? '')][] = $row;
        }

        return $groups;
    }

    /**
     * Compile the blocks of one band.
     *
     * A `table` consumes the whole row set — it is already a repeating
     * structure. Every other block repeats once per row when the band has rows,
     * which is what makes a detail band behave like a detail band.
     *
     * @return array<int, RenderedBlock>
     */
    private function compileBlocks(array $blocks, array $rows, EvaluationContext $context, bool $repeatPerRow): array
    {
        $compiled = [];

        foreach ($blocks as $block) {
            $type = BlockType::tryFrom((string) ($block['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            if ($type === BlockType::Table) {
                $compiled[] = $this->compileTable($block, $rows);

                continue;
            }

            // Header and footer bands render once even though their rows are in
            // scope for aggregates; only the detail band repeats per row.
            $repeatOver = ($repeatPerRow && $rows !== []) ? $rows : [null];

            foreach ($repeatOver as $row) {
                $rowContext = is_array($row) ? $context->withRow($row) : $context;

                $compiled[] = $this->compileBlock($type, $block, $rowContext);
            }
        }

        return $compiled;
    }

    private function compileBlock(BlockType $type, array $block, EvaluationContext $context): RenderedBlock
    {
        $attributes = $block;
        unset($attributes['type']);

        if (isset($attributes['content']) && is_string($attributes['content'])) {
            $attributes['content'] = $this->evaluator->render($attributes['content'], $context);
        }

        return new RenderedBlock($type, $attributes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function compileTable(array $block, array $rows): RenderedBlock
    {
        $columns = array_values(array_filter(
            $block['columns'] ?? [],
            fn (mixed $column): bool => is_array($column) && isset($column['field']),
        ));

        $headers = array_map(
            fn (array $column): array => [
                'label' => (string) ($column['label'] ?? $column['field']),
                'align' => (string) ($column['align'] ?? 'left'),
                'width' => $column['width'] ?? null,
            ],
            $columns,
        );

        $body = array_map(
            fn (array $row): array => array_map(
                fn (array $column): string => $this->cell($row, $column),
                $columns,
            ),
            $rows,
        );

        return new RenderedBlock(BlockType::Table, [
            'columns' => $headers,
            'rows' => array_values($body),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $column
     */
    private function cell(array $row, array $column): string
    {
        $value = $row[$column['field']] ?? null;

        if (isset($column['format']) && is_string($column['format'])) {
            $value = $this->formatter->format($value, $column['format']);
        }

        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
