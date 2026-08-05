<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferringDoctors\Pages;

use App\Filament\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateReferringDoctor extends CreateRecord
{
    protected static string $resource = ReferringDoctorResource::class;
}
