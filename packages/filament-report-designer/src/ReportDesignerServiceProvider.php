<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner;

use Illuminate\Support\ServiceProvider;
use ReportBrains\ReportDesigner\Compiler\ReportCompiler;
use ReportBrains\ReportDesigner\DataSources\DataSourceRegistry;
use ReportBrains\ReportDesigner\DataSources\ReportQueryFactory;
use ReportBrains\ReportDesigner\Expressions\ExpressionEvaluator;
use ReportBrains\ReportDesigner\Expressions\ValueFormatter;
use ReportBrains\ReportDesigner\Schema\DocumentFields;
use ReportBrains\ReportDesigner\Schema\ReportSchema;

class ReportDesignerServiceProvider extends ServiceProvider
{
    /**
     * The view namespace the package registers its Blade views under.
     */
    public const VIEW_NAMESPACE = 'report-designer';

    private const CONFIG_PATH = __DIR__.'/../config/report-designer.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'report-designer');

        $this->app->singleton(ReportSchema::class);
        $this->app->singleton(DocumentFields::class);
        $this->app->singleton(TemplateRepository::class);
        $this->app->singleton(DataSourceRegistry::class);
        $this->app->singleton(ReportQueryFactory::class);
        $this->app->singleton(ValueFormatter::class);
        $this->app->singleton(ExpressionEvaluator::class);
        $this->app->singleton(ReportCompiler::class);
        $this->app->singleton(ReportRunner::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', self::VIEW_NAMESPACE);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => config_path('report-designer.php'),
            ], 'report-designer-config');
        }
    }
}
