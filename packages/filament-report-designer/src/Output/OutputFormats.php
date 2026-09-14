<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Output;

use Closure;
use ReportBrains\ReportDesigner\Exceptions\UnsupportedFormat;
use ReportBrains\ReportDesigner\Renderers\Contracts\Renderer;
use ReportBrains\ReportDesigner\Renderers\CsvRenderer;
use ReportBrains\ReportDesigner\Renderers\HtmlRenderer;
use ReportBrains\ReportDesigner\Renderers\MarkdownRenderer;
use ReportBrains\ReportDesigner\Renderers\PdfRenderer;
use ReportBrains\ReportDesigner\Renderers\XlsxRenderer;

/**
 * The output formats a report can be rendered to, by name.
 *
 * Applications may register their own, for example a Word renderer, and it
 * becomes available everywhere a format is chosen: the facade, the export
 * action and the render command.
 */
class OutputFormats
{
    /**
     * @var array<string, array{label: string, renderer: class-string<Renderer>|Closure(): Renderer}>
     */
    private array $formats = [
        'pdf' => ['label' => 'PDF', 'renderer' => PdfRenderer::class],
        'html' => ['label' => 'HTML', 'renderer' => HtmlRenderer::class],
        'markdown' => ['label' => 'Markdown', 'renderer' => MarkdownRenderer::class],
        'csv' => ['label' => 'CSV', 'renderer' => CsvRenderer::class],
        'xlsx' => ['label' => 'Excel (XLSX)', 'renderer' => XlsxRenderer::class],
    ];

    /**
     * File extensions that name a format other than their own.
     *
     * @var array<string, string>
     */
    private array $aliases = [
        'md' => 'markdown',
        'htm' => 'html',
    ];

    /**
     * @param  class-string<Renderer>|Closure(): Renderer  $renderer
     */
    public function register(string $name, string $label, string|Closure $renderer): void
    {
        $this->formats[$name] = ['label' => $label, 'renderer' => $renderer];
    }

    public function has(string $name): bool
    {
        return isset($this->formats[$name]);
    }

    /**
     * @throws UnsupportedFormat
     */
    public function renderer(string $name): Renderer
    {
        $renderer = $this->formats[$name]['renderer'] ?? throw UnsupportedFormat::named($name, $this->names());

        return $renderer instanceof Closure ? $renderer() : app($renderer);
    }

    /**
     * The format a file extension implies, or null when none does.
     */
    public function forExtension(string $extension): ?string
    {
        $extension = strtolower(ltrim($extension, '.'));
        $name = $this->aliases[$extension] ?? $extension;

        return $this->has($name) ? $name : null;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->formats);
    }

    /**
     * Formats as label-keyed options for a picker.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(fn (array $format): string => $format['label'], $this->formats);
    }
}
