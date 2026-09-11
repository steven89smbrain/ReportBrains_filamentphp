<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

/**
 * One column a report may read.
 *
 * A field only exists because a developer explicitly exposed it. Nothing that is
 * not a Field can be selected, sorted, grouped or filtered, which is what keeps
 * a stored template from reaching data it was never meant to see.
 *
 * The label is required rather than derived from the column name: the designer
 * is used by people who should never have to read `customer_id`.
 */
class Field
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FieldType $type = FieldType::String,
        public readonly ?string $format = null,
    ) {}

    /**
     * Whether this field is reached through a relation rather than the base table.
     */
    public function isRelation(): bool
    {
        return str_contains($this->name, '.');
    }

    /**
     * The relation path, e.g. "customer" for "customer.name".
     */
    public function relationPath(): ?string
    {
        if (! $this->isRelation()) {
            return null;
        }

        $segments = explode('.', $this->name);
        array_pop($segments);

        return implode('.', $segments);
    }

    /**
     * Metadata for the editor's field picker.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'format' => $this->format,
            'is_relation' => $this->isRelation(),
        ];
    }
}
