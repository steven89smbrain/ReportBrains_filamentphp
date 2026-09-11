<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\DataSources;

/**
 * An input a report asks for at run time, such as a date range.
 */
class Parameter
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly FieldType $type = FieldType::String,
        public readonly bool $required = false,
        public readonly mixed $default = null,
    ) {}

    /**
     * Laravel validation rules for a supplied value.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        return [...$rules, ...match ($this->type) {
            FieldType::Number, FieldType::Currency => ['numeric'],
            FieldType::Date => ['date'],
            FieldType::DateTime => ['date'],
            FieldType::Boolean => ['boolean'],
            FieldType::String => ['string'],
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'default' => $this->default,
        ];
    }
}
