<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use Illuminate\Support\ServiceProvider;

class ReportDesignerServiceProvider extends ServiceProvider
{
    /**
     * The view namespace the package registers its Blade views under.
     */
    public const VIEW_NAMESPACE = 'report-designer';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', static::VIEW_NAMESPACE);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
