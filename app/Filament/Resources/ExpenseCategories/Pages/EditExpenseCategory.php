<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditExpenseCategory extends EditRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
