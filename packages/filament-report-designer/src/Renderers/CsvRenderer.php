<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use DateTimeInterface;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use Stringable;

/**
 * Renders a report's data rows as CSV — see SpreadsheetTable for what is included.
 */
class CsvRenderer implements Renderer
{
    /**
     * A cell beginning with one of these is run as a formula by spreadsheet
     * applications. Report data comes from the host's database, where a value
     * such as `=HYPERLINK(...)` could have been typed by anyone.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public function render(RenderedReport $report): string
    {
        $table = SpreadsheetTable::from($report);
        $stream = fopen('php://temp', 'r+');

        $lines = $table['header'] === [] ? $table['rows'] : [$table['header'], ...$table['rows']];

        foreach ($lines as $line) {
            fputcsv($stream, array_map($this->cell(...), $line), ',', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        // The byte-order mark makes Excel read UTF-8 correctly; other tools ignore it.
        return "\xEF\xBB\xBF".$csv;
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    private function cell(mixed $value): string|int|float
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => (int) $value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_int($value), is_float($value) => $value,
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };

        if (is_string($value) && $value !== '' && ! is_numeric($value)
            && in_array($value[0], self::FORMULA_TRIGGERS, strict: true)) {
            return "'".$value;
        }

        return $value;
    }
}
