<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferringDoctors\Pages;

use App\Filament\Resources\ReferringDoctors\ReferringDoctorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditReferringDoctor extends EditRecord
{
    protected static string $resource = ReferringDoctorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
