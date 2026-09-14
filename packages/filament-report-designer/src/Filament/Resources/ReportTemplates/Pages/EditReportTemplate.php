<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use ReportBrains\ReportDesigner\Designer\DocumentFormMapper;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Concerns\DesignsReports;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;

class EditReportTemplate extends EditRecord
{
    use DesignsReports;

    protected static string $resource = ReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->exportAction(), $this->importJsonAction(), DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...app(DocumentFormMapper::class)->toFormState((array) ($data['schema'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareTemplateAttributes($data);
    }
}
