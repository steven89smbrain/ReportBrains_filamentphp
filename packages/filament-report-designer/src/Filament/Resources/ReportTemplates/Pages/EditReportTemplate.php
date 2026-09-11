<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;

class EditReportTemplate extends EditRecord
{
    protected static string $resource = ReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
