<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->isEditable()),
        ];
    }

    protected function beforeSave(): void
    {
        if (! $this->getRecord()->isEditable()) {
            Notification::make()
                ->title('تم إغلاق الوردية')
                ->body('لا يمكن تعديل المصروف بعد إغلاق الوردية.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
