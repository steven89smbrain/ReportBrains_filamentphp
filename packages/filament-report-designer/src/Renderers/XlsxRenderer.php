<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\DateTimeCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use Stringable;

/**
 * Renders a report's data rows as an Excel workbook — see SpreadsheetTable for
 * what is included.
 *
 * Cells are typed explicitly. openspout's convenience constructor turns any
 * string starting with "=" into a formula, and report data comes from the host's
 * database, where anyone could have typed one.
 */
class XlsxRenderer implements Renderer
{
    public function render(RenderedReport $report): string
    {
        $table = SpreadsheetTable::from($report);
        $path = (string) tempnam(sys_get_temp_dir(), 'report-designer-');
        $writer = new Writer;
        $dateStyle = (new Style)->setFormat('yyyy-mm-dd hh:mm');

        try {
            $writer->openToFile($path);

            if ($table['header'] !== []) {
                $writer->addRow(new Row(
                    array_map(fn (string $label): Cell => new StringCell($label, null), $table['header']),
                    (new Style)->setFontBold(),
                ));
            }

            foreach ($table['rows'] as $values) {
                $writer->addRow(new Row(array_map(fn (mixed $value): Cell => $this->cell($value, $dateStyle), $values)));
            }

            $writer->close();

            return (string) file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    private function cell(mixed $value, Style $dateStyle): Cell
    {
        return match (true) {
            $value === null, $value === '' => new EmptyCell(null, null),
            is_bool($value) => new BooleanCell($value, null),
            is_int($value), is_float($value) => new NumericCell($value, null),
            // Decimal columns arrive as numeric strings; keep them numbers.
            is_string($value) && is_numeric($value) => new NumericCell($value + 0, null),
            $value instanceof DateTimeInterface => new DateTimeCell($value, $dateStyle),
            is_scalar($value), $value instanceof Stringable => new StringCell((string) $value, null),
            default => new EmptyCell(null, null),
        };
    }
}
