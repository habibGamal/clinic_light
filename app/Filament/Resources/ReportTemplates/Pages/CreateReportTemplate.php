<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates\Pages;

use App\Filament\Resources\ReportTemplates\ReportTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateReportTemplate extends CreateRecord
{
    protected static string $resource = ReportTemplateResource::class;
}
