<?php

declare(strict_types=1);

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Patients\PatientResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;
}
