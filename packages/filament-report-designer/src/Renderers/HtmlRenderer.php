<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use ReportBrains\ReportDesigner\Compiler\RenderedBand;
use ReportBrains\ReportDesigner\Compiler\RenderedBlock;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\Schema\BlockType;

/**
 * Renders a report as HTML, used both for the designer preview and later as
 * the input to PDF.
 *
 * Every value is escaped. Report data comes from the host application's
 * database, and a customer name containing a script tag must not become script
 * in someone else's browser.
 */
class HtmlRenderer implements Renderer
{
    public function __construct(private readonly bool $fragment = false) {}

    public function render(RenderedReport $report): string
    {
        $body = [];

        foreach ($report->flowBands() as $band) {
            $rendered = $this->band($band);

            if ($rendered !== '') {
                $body[] = $rendered;
            }
        }

        $content = implode("\n", $body);

        return $this->fragment
            ? $content
            : $this->document($report, $content);
    }

    /**
     * Render only the given bands, with no document around them — used for PDF
     * page headers and footers.
     *
     * @param  array<int, RenderedBand>  $bands
     */
    public function renderBands(array $bands): string
    {
        return implode("\n", array_filter(
            array_map($this->band(...), $bands),
            fn (string $band): bool => $band !== '',
        ));
    }

    public function extension(): string
    {
        return 'html';
    }

    public function mimeType(): string
    {
        return 'text/html';
    }

    private function document(RenderedReport $report, string $content): string
    {
        $title = $this->escape($report->title);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>{$title}</title>
        <style>{$this->styles()}</style>
        </head>
        <body>
        {$content}
        </body>
        </html>
        HTML;
    }

    private function styles(): string
    {
        return 'body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:2rem;color:#111}'
            .'table{border-collapse:collapse;width:100%;margin:0.5rem 0}'
            .'th,td{border:1px solid #d4d4d8;padding:0.4rem 0.6rem;text-align:left}'
            .'th{background:#f4f4f5;font-weight:600}'
            .'hr{border:0;border-top:1px solid #d4d4d8;margin:1rem 0}'
            .'.rb-band{margin-bottom:1rem}';
    }

    private function band(RenderedBand $band): string
    {
        $blocks = array_filter(array_map($this->block(...), $band->blocks), fn (string $b): bool => $b !== '');

        if ($blocks === []) {
            return '';
        }

        $name = $this->escape($band->name->value);

        return sprintf('<section class="rb-band rb-band--%s">%s</section>', $name, implode("\n", $blocks));
    }

    private function block(RenderedBlock $block): string
    {
        return match ($block->type) {
            BlockType::Heading => $this->heading($block),
            BlockType::Text => $this->text($block),
            BlockType::Table => $this->table($block),
            BlockType::Divider => '<hr>',
            BlockType::Spacer => sprintf('<div style="height:%dpx"></div>', (int) $block->get('height', 16)),
        };
    }

    private function heading(RenderedBlock $block): string
    {
        $level = max(1, min(6, (int) $block->get('level', 1)));
        $content = $this->escape((string) $block->get('content', ''));

        if ($content === '') {
            return '';
        }

        return sprintf('<h%d%s>%s</h%d>', $level, $this->alignStyle($block), $content, $level);
    }

    private function text(RenderedBlock $block): string
    {
        $content = $this->escape((string) $block->get('content', ''));

        if ($content === '') {
            return '';
        }

        if ($block->get('bold', false) === true) {
            $content = "<strong>{$content}</strong>";
        }

        if ($block->get('italic', false) === true) {
            $content = "<em>{$content}</em>";
        }

        return sprintf('<p%s>%s</p>', $this->alignStyle($block), $content);
    }

    private function table(RenderedBlock $block): string
    {
        $columns = $block->get('columns', []);
        $rows = $block->get('rows', []);

        if ($columns === []) {
            return '';
        }

        $head = implode('', array_map(
            fn (array $column): string => sprintf(
                '<th%s%s>%s</th>',
                $this->alignAttribute((string) ($column['align'] ?? 'left')),
                $column['width'] !== null ? sprintf(' style="width:%s"', $this->escape((string) $column['width'])) : '',
                $this->escape((string) $column['label']),
            ),
            $columns,
        ));

        $body = '';

        foreach ($rows as $row) {
            $cells = '';

            foreach (array_values($row) as $index => $cell) {
                $cells .= sprintf(
                    '<td%s>%s</td>',
                    $this->alignAttribute((string) ($columns[$index]['align'] ?? 'left')),
                    $this->escape((string) $cell),
                );
            }

            $body .= "<tr>{$cells}</tr>";
        }

        return "<table><thead><tr>{$head}</tr></thead><tbody>{$body}</tbody></table>";
    }

    private function alignStyle(RenderedBlock $block): string
    {
        $align = (string) $block->get('align', 'left');

        return in_array($align, ['center', 'right'], strict: true)
            ? sprintf(' style="text-align:%s"', $align)
            : '';
    }

    private function alignAttribute(string $align): string
    {
        return in_array($align, ['center', 'right'], strict: true)
            ? sprintf(' style="text-align:%s"', $align)
            : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
