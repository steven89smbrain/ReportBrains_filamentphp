<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use JsonException;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;
use ReportBrains\ReportDesigner\Exceptions\TemplateNotFound;
use ReportBrains\ReportDesigner\Models\ReportTemplate;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

/**
 * Loads report documents from either of the two places they may live: the
 * database (edited in the panel) or JSON files on disk (shipped with the
 * application and committed to version control).
 *
 * Both return the same validated array, so callers never need to know which
 * source a document came from.
 */
class TemplateRepository
{
    public function __construct(private readonly ReportSchema $schema) {}

    /**
     * Load a stored template by key, honouring ownership and tenancy.
     *
     * @throws TemplateNotFound
     */
    public function find(string $key): ReportTemplate
    {
        return ReportTemplate::query()
            ->visible()
            ->key($key)
            ->first() ?? throw TemplateNotFound::key($key);
    }

    public function findOrNull(string $key): ?ReportTemplate
    {
        return ReportTemplate::query()->visible()->key($key)->first();
    }

    /**
     * Read a template from a JSON file inside the configured directory.
     *
     * @return array<string, mixed>
     *
     * @throws TemplateNotFound|InvalidReportSchema
     */
    public function fromFile(string $path): array
    {
        $resolved = $this->resolveWithinTemplateDirectory($path);

        $contents = file_get_contents($resolved);

        if ($contents === false) {
            throw TemplateNotFound::path($path);
        }

        try {
            $document = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidReportSchema(
                ['file' => [$exception->getMessage()]],
                "The template file [{$path}] is not valid JSON.",
            );
        }

        if (! is_array($document)) {
            throw new InvalidReportSchema(
                ['file' => ['The template file must contain a JSON object.']],
            );
        }

        return $this->schema->validate($document);
    }

    /**
     * Read a file template by key, e.g. "sales-monthly" => sales-monthly.json.
     *
     * @return array<string, mixed>
     */
    public function fromFileKey(string $key): array
    {
        return $this->fromFile($this->templateDirectory().DIRECTORY_SEPARATOR.$key.'.json');
    }

    /**
     * Resolve a path and refuse anything that escapes the template directory.
     *
     * Template paths can reach this method from configuration or from a request,
     * so "../../.env" must not resolve to a readable file.
     *
     * @throws TemplateNotFound
     */
    private function resolveWithinTemplateDirectory(string $path): string
    {
        $directory = realpath($this->templateDirectory());

        if ($directory === false) {
            throw TemplateNotFound::path($path);
        }

        $resolved = realpath($path);

        if ($resolved === false || ! is_file($resolved)) {
            throw TemplateNotFound::path($path);
        }

        if (! str_starts_with($resolved, $directory.DIRECTORY_SEPARATOR)) {
            throw TemplateNotFound::outsideTemplateDirectory($path);
        }

        return $resolved;
    }

    private function templateDirectory(): string
    {
        return (string) config('report-designer.file_templates.path', resource_path('reports'));
    }
}
