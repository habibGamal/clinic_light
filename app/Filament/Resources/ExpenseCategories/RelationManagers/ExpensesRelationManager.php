<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseCategories\RelationManagers;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Resources\RelationManagers\RelationManager;

final class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $relatedResource = ExpenseResource::class;

    protected static ?string $title = 'المصروفات التابعة';
}
