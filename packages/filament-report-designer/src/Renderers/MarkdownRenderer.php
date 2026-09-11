<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Renderers;

use ReportBrains\ReportDesigner\Compiler\RenderedBand;
use ReportBrains\ReportDesigner\Compiler\RenderedBlock;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\Schema\BlockType;

/**
 * Renders a report as Markdown.
 *
 * Page setup and the page header/footer bands are skipped: Markdown has no
 * pages, and inventing a representation for them would produce output that
 * looks broken rather than merely plain.
 */
class MarkdownRenderer implements Renderer
{
    public function render(RenderedReport $report): string
    {
        $parts = [];

        foreach ($report->flowBands() as $band) {
            $rendered = $this->band($band);

            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return implode("\n\n", $parts)."\n";
    }

    public function extension(): string
    {
        return 'md';
    }

    public function mimeType(): string
    {
        return 'text/markdown';
    }

    private function band(RenderedBand $band): string
    {
        $blocks = array_filter(array_map($this->block(...), $band->blocks), fn (string $b): bool => $b !== '');

        return implode("\n\n", $blocks);
    }

    private function block(RenderedBlock $block): string
    {
        return match ($block->type) {
            BlockType::Heading => $this->heading($block),
            BlockType::Text => $this->text($block),
            BlockType::Table => $this->table($block),
            BlockType::Divider => '---',
            BlockType::Spacer => '&nbsp;',
        };
    }

    private function heading(RenderedBlock $block): string
    {
        $level = max(1, min(6, (int) $block->get('level', 1)));
        $content = $this->escape((string) $block->get('content', ''));

        return $content === '' ? '' : str_repeat('#', $level).' '.$content;
    }

    private function text(RenderedBlock $block): string
    {
        $content = $this->escape((string) $block->get('content', ''));

        if ($content === '') {
            return '';
        }

        if ($block->get('bold', false) === true) {
            $content = "**{$content}**";
        }

        if ($block->get('italic', false) === true) {
            $content = "_{$content}_";
        }

        return $content;
    }

    private function table(RenderedBlock $block): string
    {
        $columns = $block->get('columns', []);
        $rows = $block->get('rows', []);

        if ($columns === []) {
            return '';
        }

        $lines = [
            '| '.implode(' | ', array_map(
                fn (array $column): string => $this->escapeCell((string) $column['label']),
                $columns,
            )).' |',
            '| '.implode(' | ', array_map(
                fn (array $column): string => $this->alignment((string) ($column['align'] ?? 'left')),
                $columns,
            )).' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map($this->escapeCell(...), $row)).' |';
        }

        return implode("\n", $lines);
    }

    private function alignment(string $align): string
    {
        return match ($align) {
            'center' => ':---:',
            'right' => '---:',
            default => '---',
        };
    }

    /**
     * Escape Markdown control characters so data cannot alter the structure.
     */
    private function escape(string $value): string
    {
        return preg_replace('/([\\\\`*_\[\]<>])/', '\\\\$1', $value) ?? $value;
    }

    /**
     * Cells additionally escape the pipe, which would otherwise split a column.
     */
    private function escapeCell(string $value): string
    {
        return str_replace(["\n", '|'], [' ', '\\|'], $this->escape($value));
    }
}
