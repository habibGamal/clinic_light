<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts\Pages;

use App\Filament\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;
}
