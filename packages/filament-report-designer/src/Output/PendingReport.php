<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Output;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ReportBrains\ReportDesigner\Compiler\RenderedReport;
use ReportBrains\ReportDesigner\Jobs\RenderReport;
use ReportBrains\ReportDesigner\ReportRunner;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A report ready to run: a validated document plus the parameters to run it with.
 *
 * Nothing touches the data source until an output is asked for.
 */
class PendingReport
{
    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        private readonly array $document,
        private readonly ReportRunner $runner,
        private readonly OutputFormats $formats,
        private readonly array $parameters = [],
    ) {}

    /**
     * Run with these parameters, merged over any given before.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function with(array $parameters): self
    {
        return new self($this->document, $this->runner, $this->formats, [...$this->parameters, ...$parameters]);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->document;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function compile(): RenderedReport
    {
        return $this->runner->compile($this->document, $this->parameters);
    }

    public function render(string $format): string
    {
        return $this->runner->render($this->document, $this->formats->renderer($format), $this->parameters);
    }

    public function toPdf(): string
    {
        return $this->render('pdf');
    }

    public function toHtml(): string
    {
        return $this->render('html');
    }

    public function toMarkdown(): string
    {
        return $this->render('markdown');
    }

    public function toCsv(): string
    {
        return $this->render('csv');
    }

    public function toXlsx(): string
    {
        return $this->render('xlsx');
    }

    /**
     * Render and store the report, returning the path it was stored at.
     *
     * The format is taken from the path's extension unless given.
     */
    public function save(string $path, ?string $disk = null, ?string $format = null): string
    {
        $format ??= $this->formatFor($path);

        Storage::disk($disk)->put($path, $this->render($format));

        return $path;
    }

    public function download(?string $filename = null, string $format = 'pdf'): StreamedResponse
    {
        $renderer = $this->formats->renderer($format);
        $content = $this->runner->render($this->document, $renderer, $this->parameters);

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $filename ?? $this->defaultFilename($renderer->extension()),
            ['Content-Type' => $renderer->mimeType()],
        );
    }

    /**
     * Render and store the report on the queue.
     *
     * The job runs as the given user — the authenticated one by default — so
     * data source scopes keep applying outside the request. The document is
     * captured now, so editing the template meanwhile does not change the run.
     */
    public function queue(string $path, ?string $disk = null, ?string $format = null, ?Authenticatable $as = null, ?string $guard = null): PendingDispatch
    {
        $guard ??= Auth::getDefaultDriver();
        $as ??= Auth::guard($guard)->user();

        return RenderReport::dispatch(
            $this->document,
            $this->parameters,
            $format ?? $this->formatFor($path),
            $path,
            $disk,
            $as?->getAuthIdentifier(),
            $guard,
        );
    }

    private function formatFor(string $path): string
    {
        return $this->formats->forExtension(pathinfo($path, PATHINFO_EXTENSION)) ?? 'pdf';
    }

    private function defaultFilename(string $extension): string
    {
        return sprintf('%s-%s.%s', $this->document['key'] ?? 'report', now()->format('Y-m-d'), $extension);
    }
}
