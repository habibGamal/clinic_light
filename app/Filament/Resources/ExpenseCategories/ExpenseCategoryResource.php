<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseCategories;

use App\Filament\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\EditExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseCategories\RelationManagers\ExpensesRelationManager;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'تصنيفات المصروفات';

    protected static ?string $modelLabel = 'تصنيف مصروف';

    protected static ?string $pluralModelLabel = 'تصنيفات المصروفات';

    protected static string|UnitEnum|null $navigationGroup = 'العمليات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات التصنيف')
                ->schema([
                    Grid::make(1)->schema([
                        TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم التصنيف')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expenses_count')
                    ->label('عدد المصروفات')
                    ->counts('expenses')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'expenses' => ExpensesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseCategories::route('/'),
            'create' => CreateExpenseCategory::route('/create'),
            'edit' => EditExpenseCategory::route('/{record}/edit'),
        ];
    }
}
