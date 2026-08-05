<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\Pages;

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePatientVisit extends CreateRecord
{
    protected static string $resource = PatientVisitResource::class;
}
