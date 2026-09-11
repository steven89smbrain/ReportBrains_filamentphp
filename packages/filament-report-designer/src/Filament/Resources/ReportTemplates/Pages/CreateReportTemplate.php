<?php

declare(strict_types=1);

namespace ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\Pages;

use Filament\Resources\Pages\CreateRecord;
use ReportBrains\ReportDesigner\Filament\Resources\ReportTemplates\ReportTemplateResource;

class CreateReportTemplate extends CreateRecord
{
    protected static string $resource = ReportTemplateResource::class;
}
