<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Schema;

use Illuminate\Support\Facades\Validator;
use ReportBrains\ReportDesigner\Exceptions\InvalidReportSchema;

/**
 * Validator for the report document format.
 *
 * The document is the single source of truth for a report: Markdown, HTML, PDF
 * and spreadsheet output are all rendered from it. Everything that reaches
 * storage passes through here first, because a malformed document that is saved
 * once will fail much later, at render time, where the cause is far less clear.
 */
class ReportSchema
{
    /**
     * Bump whenever the document format changes in a way older documents cannot
     * satisfy, and add an upgrade path for documents written against version 1.
     */
    public const CURRENT_VERSION = 1;

    /**
     * Validate a report document.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed> The document, unchanged, once it is known to be valid.
     *
     * @throws InvalidReportSchema
     */
    public function validate(array $document): array
    {
        $validator = Validator::make($document, $this->rules());

        $errors = $validator->fails() ? $validator->errors()->messages() : [];

        // Blocks are polymorphic — each `type` has its own shape — so they are
        // checked separately rather than expressed as one flat rule set.
        if ($errors === []) {
            $errors = $this->validateBands($document['bands']);
        }

        if ($errors !== []) {
            throw new InvalidReportSchema($errors);
        }

        return $document;
    }

    public function isValid(array $document): bool
    {
        try {
            $this->validate($document);

            return true;
        } catch (InvalidReportSchema) {
            return false;
        }
    }

    /**
     * Rules for everything except the contents of each band.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'schema_version' => ['required', 'integer', 'in:'.self::CURRENT_VERSION],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:255'],

            'params' => ['sometimes', 'array'],
            'params.*.name' => ['required', 'string', 'regex:/^[a-z_][a-z0-9_]*$/'],
            'params.*.type' => ['required', 'string', 'in:string,number,date,boolean,select'],
            'params.*.label' => ['sometimes', 'string', 'max:255'],
            'params.*.required' => ['sometimes', 'boolean'],
            'params.*.source' => ['sometimes', 'string'],

            'data' => ['required', 'array'],
            'data.source' => ['required', 'string', 'max:255'],
            'data.filters' => ['sometimes', 'array'],
            'data.sort' => ['sometimes', 'array'],
            'data.sort.*.field' => ['required', 'string'],
            'data.sort.*.dir' => ['sometimes', 'string', 'in:asc,desc'],
            'data.group_by' => ['sometimes', 'array'],
            'data.group_by.*' => ['string'],

            'page' => ['sometimes', 'array'],
            'page.size' => ['sometimes', 'string', 'in:A4,A5,A3,Letter,Legal'],
            'page.orientation' => ['sometimes', 'string', 'in:portrait,landscape'],
            'page.margin' => ['sometimes', 'array'],
            'page.margin.top' => ['sometimes', 'numeric', 'min:0'],
            'page.margin.right' => ['sometimes', 'numeric', 'min:0'],
            'page.margin.bottom' => ['sometimes', 'numeric', 'min:0'],
            'page.margin.left' => ['sometimes', 'numeric', 'min:0'],

            'bands' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * Check every band name and the blocks inside each one.
     *
     * @param  array<string, mixed>  $bands
     * @return array<string, array<int, string>>
     */
    private function validateBands(array $bands): array
    {
        $errors = [];

        foreach ($bands as $name => $blocks) {
            $path = "bands.{$name}";

            if (BandName::tryFrom((string) $name) === null) {
                $errors[$path] = [sprintf(
                    'Unknown band "%s". Expected one of: %s.',
                    $name,
                    implode(', ', BandName::values()),
                )];

                continue;
            }

            if (! is_array($blocks)) {
                $errors[$path] = ["Band \"{$name}\" must be an array of blocks."];

                continue;
            }

            foreach (array_values($blocks) as $index => $block) {
                $errors += $this->validateBlock($block, "{$path}.{$index}");
            }
        }

        return $errors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function validateBlock(mixed $block, string $path): array
    {
        if (! is_array($block) || ! isset($block['type']) || ! is_string($block['type'])) {
            return [$path => ["Block at {$path} must be an object with a \"type\"."]];
        }

        $type = BlockType::tryFrom($block['type']);

        if ($type === null) {
            return [$path.'.type' => [sprintf(
                'Unknown block type "%s". Expected one of: %s.',
                $block['type'],
                implode(', ', BlockType::values()),
            )]];
        }

        $validator = Validator::make($block, $type->rules());

        if (! $validator->fails()) {
            return [];
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors["{$path}.{$key}"] = $messages;
        }

        return $errors;
    }
}
