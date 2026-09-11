<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Compiler;

use ReportBrains\ReportDesigner\Schema\BandName;

/**
 * A report reduced to pure data, ready for any renderer.
 */
class RenderedReport
{
    /**
     * @param  array<int, RenderedBand>  $bands  In document order.
     * @param  array<string, mixed>  $page  Paper setup, for paginated renderers.
     */
    public function __construct(
        public readonly string $title,
        public readonly array $bands,
        public readonly array $page = [],
        public readonly int $rowCount = 0,
    ) {}

    /**
     * Bands excluding those only meaningful in paginated output.
     *
     * @return array<int, RenderedBand>
     */
    public function flowBands(): array
    {
        return array_values(array_filter(
            $this->bands,
            fn (RenderedBand $band): bool => ! $band->name->isPaginatedOnly(),
        ));
    }

    public function firstBand(BandName $name): ?RenderedBand
    {
        foreach ($this->bands as $band) {
            if ($band->name === $name) {
                return $band;
            }
        }

        return null;
    }
}
