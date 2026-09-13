<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
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
    public const ID = 'report-designer';

    private bool $jsonEditor = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * Show the JSON tab and the "Import JSON" action.
     *
     * Useful for developers; switch it off for panels used only by people who
     * design reports visually and should never see the underlying document.
     */
    public function jsonEditor(bool $enabled = true): static
    {
        $this->jsonEditor = $enabled;

        return $this;
    }

    public function hasJsonEditor(): bool
    {
        return $this->jsonEditor;
    }

    /**
     * Whether the panel currently in use has the JSON editor switched on.
     */
    public static function jsonEditorEnabled(): bool
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel === null || ! $panel->hasPlugin(self::ID)) {
            return true;
        }

        /** @var static $plugin */
        $plugin = $panel->getPlugin(self::ID);

        return $plugin->hasJsonEditor();
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Filament/Resources',
            for: 'ReportBrains\\ReportDesigner\\Filament\\Resources',
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
