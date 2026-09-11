<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use ReportBrains\ReportDesigner\DataSources\EloquentSource;
use ReportBrains\ReportDesigner\DataSources\FieldType;
use ReportBrains\ReportDesigner\Facades\ReportData;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerReportDataSources();
    }

    /**
     * Expose data to the report designer.
     *
     * Only the fields listed here can be reached by a report, no matter what a
     * stored template asks for — which is why `password` and `remember_token`
     * are absent rather than merely hidden.
     */
    private function registerReportDataSources(): void
    {
        ReportData::eloquent('users', User::class, function (EloquentSource $source): void {
            $source
                ->setLabel('Users')
                ->addField('name', 'Name')
                ->addField('email', 'Email address')
                ->addField('created_at', 'Registered at', FieldType::DateTime)
                ->addParameter('registered_from', 'Registered from', FieldType::Date)
                ->maxRows(1000);
        });
    }
}
