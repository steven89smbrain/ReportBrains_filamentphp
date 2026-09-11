<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Schema;

/**
 * The regions of a report, in the order they are laid out.
 *
 * Bands are what separate a report from a page builder: Detail is repeated once
 * per row of data, and the group bands are repeated once per distinct value of
 * the grouping columns.
 */
enum BandName: string
{
    case DocumentHeader = 'document_header';
    case PageHeader = 'page_header';
    case GroupHeader = 'group_header';
    case Detail = 'detail';
    case GroupFooter = 'group_footer';
    case PageFooter = 'page_footer';
    case DocumentFooter = 'document_footer';

    /**
     * Bands that are rendered once per row or group rather than once per report.
     *
     * @return array<int, self>
     */
    public static function repeating(): array
    {
        return [self::GroupHeader, self::Detail, self::GroupFooter];
    }

    /**
     * Bands that only exist in paginated output such as PDF.
     *
     * @return array<int, self>
     */
    public static function paginatedOnly(): array
    {
        return [self::PageHeader, self::PageFooter];
    }

    public function isRepeating(): bool
    {
        return in_array($this, self::repeating(), strict: true);
    }

    public function isPaginatedOnly(): bool
    {
        return in_array($this, self::paginatedOnly(), strict: true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
