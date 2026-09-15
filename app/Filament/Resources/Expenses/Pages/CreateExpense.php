<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Pages;

use App\Enums\ShiftStatus;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Shift;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

final class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        $activeShift = Shift::query()
            ->where('user_id', auth()->id())
            ->where('status', ShiftStatus::Open)
            ->latest('opened_at')
            ->first();

        if (! $activeShift) {
            Notification::make()
                ->title('لا توجد وردية مفتوحة')
                ->body('لا يمكن إنشاء مصروف بدون وجود وردية مفتوحة. يرجى فتح وردية أولاً.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['shift_id'] = $activeShift->id;

        return $data;
    }
}
