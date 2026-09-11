<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Schema;

/**
 * Finds the data fields a document's blocks refer to.
 *
 * Used to decide what to read from the data source, and to check those names
 * against the source's whitelist before anything is fetched.
 */
class DocumentFields
{
    /**
     * @param  array<string, mixed>  $document
     * @return array<int, string>
     */
    public function referencedBy(array $document): array
    {
        $fields = [];

        foreach ($document['bands'] ?? [] as $blocks) {
            if (! is_array($blocks)) {
                continue;
            }

            foreach ($blocks as $block) {
                $fields = [...$fields, ...$this->referencedByBlock($block)];
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return array<int, string>
     */
    private function referencedByBlock(mixed $block): array
    {
        if (! is_array($block) || ! isset($block['type'])) {
            return [];
        }

        return match (BlockType::tryFrom((string) $block['type'])) {
            BlockType::Table => array_values(array_filter(
                array_column($block['columns'] ?? [], 'field'),
                is_string(...),
            )),
            default => [],
        };
    }
}
