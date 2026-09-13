<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages;

use Filament\Resources\Pages\CreateRecord;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Concerns\DesignsReports;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;

class CreateReportTemplate extends CreateRecord
{
    use DesignsReports;

    protected static string $resource = ReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->importJsonAction()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareTemplateAttributes($data);
    }
}
