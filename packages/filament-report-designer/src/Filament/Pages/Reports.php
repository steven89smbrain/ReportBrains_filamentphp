<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use ReportBrains\ReportDesigner\ReportDesignerServiceProvider;

/**
 * Placeholder landing page, replaced by the template resource in M1.
 *
 * It exists so the plugin wiring can be verified end to end before any
 * report functionality is built.
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected string $view = ReportDesignerServiceProvider::VIEW_NAMESPACE.'::filament.pages.reports';

    public function getTitle(): string
    {
        return 'Report Designer';
    }
}
