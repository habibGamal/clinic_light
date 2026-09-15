<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\Pages;

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\Shift;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

final class CreatePatientVisit extends CreateRecord
{
    protected static string $resource = PatientVisitResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $activeShift = Shift::query()
            ->where('user_id', auth()->id())
            ->where('status', ShiftStatus::Open)
            ->latest('opened_at')
            ->first();

        if (! $activeShift) {
            Notification::make()
                ->title('لا توجد وردية مفتوحة')
                ->body('لا يمكن إنشاء زيارة بدون وجود وردية مفتوحة. يرجى فتح وردية أولاً.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['shift_id'] = $activeShift->id;
        $data['status'] = VisitStatus::Waiting;

        return $data;
    }
}
