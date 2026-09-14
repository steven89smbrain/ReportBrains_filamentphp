<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Schema\BandName;
use ReportBrains\ReportDesigner\Schema\BlockType;

/**
 * The data rows of a report, flattened into one table for spreadsheet output.
 *
 * Spreadsheets hold data, not layout, so only tables in the detail band are
 * exported — headings, totals text and header tables are left out. Values are
 * the raw ones, not the formatted text a document shows. A grouped report gets
 * a leading "Group" column, so the grouping survives sorting and filtering in
 * the spreadsheet.
 */
final class SpreadsheetTable
{
    /**
     * @return array{header: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public static function from(RenderedReport $report): array
    {
        $header = null;
        $rows = [];
        $grouped = false;

        foreach ($report->bands as $band) {
            if ($band->name !== BandName::Detail) {
                continue;
            }

            foreach ($band->blocks as $block) {
                if ($block->type !== BlockType::Table) {
                    continue;
                }

                $header ??= array_map(fn (array $column): string => (string) $column['label'], $block->get('columns', []));
                $grouped = $grouped || $band->group !== null;

                foreach ($block->get('values', []) as $values) {
                    $rows[] = [$band->group, $values];
                }
            }
        }

        return [
            'header' => $grouped ? ['Group', ...($header ?? [])] : ($header ?? []),
            'rows' => array_map(
                fn (array $row): array => $grouped ? [$row[0], ...$row[1]] : $row[1],
                $rows,
            ),
        ];
    }
}
