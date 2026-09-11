<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Schema;

/**
 * Block types understood by schema version 1.
 *
 * Every block in a band carries a `type` matching one of these values. Adding a
 * case here is a schema change: bump ReportSchema::CURRENT_VERSION alongside it.
 */
enum BlockType: string
{
    case Heading = 'heading';
    case Text = 'text';
    case Table = 'table';
    case Divider = 'divider';
    case Spacer = 'spacer';

    /**
     * Validation rules for this block's own keys, excluding `type`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Heading => [
                'level' => ['required', 'integer', 'between:1,6'],
                'content' => ['required', 'string'],
                'align' => ['sometimes', 'string', 'in:left,center,right'],
            ],
            self::Text => [
                'content' => ['required', 'string'],
                'align' => ['sometimes', 'string', 'in:left,center,right'],
                'bold' => ['sometimes', 'boolean'],
                'italic' => ['sometimes', 'boolean'],
            ],
            self::Table => [
                'columns' => ['required', 'array', 'min:1'],
                'columns.*.field' => ['required', 'string'],
                'columns.*.label' => ['sometimes', 'string'],
                'columns.*.align' => ['sometimes', 'string', 'in:left,center,right'],
                'columns.*.width' => ['sometimes', 'string'],
                'columns.*.format' => ['sometimes', 'string'],
            ],
            self::Divider => [],
            self::Spacer => [
                'height' => ['sometimes', 'integer', 'min:0', 'max:500'],
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
