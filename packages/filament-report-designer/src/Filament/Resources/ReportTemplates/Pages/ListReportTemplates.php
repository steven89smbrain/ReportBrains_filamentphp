<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;

class ListReportTemplates extends ListRecords
{
    protected static string $resource = ReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
