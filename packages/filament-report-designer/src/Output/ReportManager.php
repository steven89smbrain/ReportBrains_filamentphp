<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Output;

use ReportBrains\ReportDesigner\ReportRunner;
use ReportBrains\ReportDesigner\Schema\ReportSchema;
use ReportBrains\ReportDesigner\TemplateRepository;

/**
 * Entry point for running reports from code, behind the `Report` facade.
 */
class ReportManager
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly ReportSchema $schema,
        private readonly ReportRunner $runner,
        private readonly OutputFormats $formats,
    ) {}

    /**
     * A template stored in the database, honouring ownership and tenancy.
     */
    public function template(string $key): PendingReport
    {
        return $this->pending($this->templates->find($key)->schema);
    }

    /**
     * A template shipped as a JSON file: a key inside the configured template
     * directory, or a path to a `.json` file within it.
     */
    public function file(string $keyOrPath): PendingReport
    {
        return $this->pending(str_ends_with($keyOrPath, '.json')
            ? $this->templates->fromFile($keyOrPath)
            : $this->templates->fromFileKey($keyOrPath));
    }

    /**
     * A document built in code. It is validated before anything runs.
     *
     * @param  array<string, mixed>  $document
     */
    public function document(array $document): PendingReport
    {
        return $this->pending($this->schema->validate($document));
    }

    public function formats(): OutputFormats
    {
        return $this->formats;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function pending(array $document): PendingReport
    {
        return new PendingReport($document, $this->runner, $this->formats);
    }
}
