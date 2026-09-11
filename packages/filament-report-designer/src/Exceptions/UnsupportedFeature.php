<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Exceptions;

use RuntimeException;

/**
 * Raised when a document asks for something the current version cannot do.
 *
 * Preferred over quietly producing a partial result: a report that silently
 * drops a grouping level is worse than one that refuses to run.
 */
class UnsupportedFeature extends RuntimeException
{
    public static function nestedGrouping(int $levels): self
    {
        return new self(
            "This version groups by one field; the document asks for {$levels}. ".
            'Remove the extra entries from data.group_by.'
        );
    }
}
