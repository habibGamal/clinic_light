<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\Pages;

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPatientVisit extends ViewRecord
{
    protected static string $resource = PatientVisitResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
