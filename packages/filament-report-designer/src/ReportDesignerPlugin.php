<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Entry point that wires the report designer into a Filament panel.
 *
 * Register it from a panel provider:
 *
 * ```php
 * $panel->plugin(ReportDesignerPlugin::make());
 * ```
 */
class ReportDesignerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'report-designer';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'ReportBrains\\ReportDesigner\\Filament\\Resources',
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Pages',
                for: 'ReportBrains\\ReportDesigner\\Filament\\Pages',
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
