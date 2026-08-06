<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'المصروفات';

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصروفات';

    protected static string|UnitEnum|null $navigationGroup = 'العمليات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المصروف')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('expense_category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Grid::make(1)->schema([
                                    TextInput::make('name')
                                        ->label('اسم التصنيف')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                            ]),
                        TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required()
                            ->prefix('EGP'),
                        Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('shift.id')
                    ->label('الوردية')
                    ->placeholder('بدون وردية')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('بواسطة')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
