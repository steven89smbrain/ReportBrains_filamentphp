<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Expressions;

/**
 * Placeholders for page numbers, which only exist once a PDF is laid out.
 *
 * The evaluator emits these tokens for `{{ page.number }}` and `{{ page.total }}`;
 * the PDF renderer swaps them for the markup the browser fills with its own page
 * counters. They use private-use characters so escaping leaves them intact and no
 * report data can collide with them.
 */
final class PageToken
{
    public const NUMBER = "\u{E000}page-number\u{E000}";

    public const TOTAL = "\u{E000}page-total\u{E000}";

    public static function isToken(mixed $value): bool
    {
        return $value === self::NUMBER || $value === self::TOTAL;
    }

    public static function toChromeSpans(string $html): string
    {
        return str_replace(
            [self::NUMBER, self::TOTAL],
            ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
            $html,
        );
    }
}
