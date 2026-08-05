<?php

declare(strict_types=1);

namespace App\Filament\Resources\Shifts;

use App\Enums\ShiftStatus;
use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Shift;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'الورديات';

    protected static ?string $modelLabel = 'وردية';

    protected static ?string $pluralModelLabel = 'الورديات';

    protected static string|UnitEnum|null $navigationGroup = 'العمليات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات الوردية')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('user_id')
                            ->label('الفني')
                            ->relationship('user', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(ShiftStatus::class)
                            ->default(ShiftStatus::Open)
                            ->required(),
                        DateTimePicker::make('opened_at')
                            ->label('وقت الفتح')
                            ->required()
                            ->default(now()),
                        DateTimePicker::make('closed_at')
                            ->label('وقت الإغلاق'),
                    ]),
                ]),
            Section::make('البيانات المالية')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('opening_balance')
                            ->label('الرصيد الافتتاحي')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('closing_balance')
                            ->label('الرصيد الختامي')
                            ->numeric(),
                        TextInput::make('actual_cash')
                            ->label('النقدي الفعلي')
                            ->numeric(),
                        TextInput::make('difference')
                            ->label('الفرق')
                            ->numeric(),
                    ]),
                ]),
            Section::make()
                ->schema([
                    Textarea::make('notes')
                        ->label('ملاحظات'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('الفني')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('opening_balance')
                    ->label('الافتتاحي')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('closing_balance')
                    ->label('الختامي')
                    ->money('EGP'),
                TextColumn::make('opened_at')
                    ->label('وقت الفتح')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('وقت الإغلاق')
                    ->dateTime(),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ShiftStatus::class),
            ])
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
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }
}
