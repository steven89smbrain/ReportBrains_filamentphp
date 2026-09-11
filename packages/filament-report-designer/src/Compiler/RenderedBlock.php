<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Compiler;

use ReportBrains\ReportDesigner\Schema\BlockType;

/**
 * A block with everything resolved: no expressions, no field references.
 *
 * Renderers only translate these into their own syntax, which is why they need
 * neither a database nor an evaluator.
 */
class RenderedBlock
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly BlockType $type,
        public readonly array $attributes = [],
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
