<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;
}
