<?php

declare(strict_types=1);

namespace App\Filament\Resources\Patients;

use App\Enums\Gender;
use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\Patient;
use BackedEnum;
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

final class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $navigationLabel = 'المرضى';

    protected static ?string $modelLabel = 'مريض';

    protected static ?string $pluralModelLabel = 'المرضى';

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المرضى';

    public static function getGloballySearchableAttributes(): array
    {
        return ['full_name', 'phone'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('بيانات المريض')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('full_name')
                            ->label('الاسم الكامل')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        TextInput::make('age')
                            ->label('العمر')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(150),
                        Select::make('gender')
                            ->label('الجنس')
                            ->options(Gender::class),
                        Textarea::make('address')
                            ->label('العنوان')
                            ->columnSpanFull(),
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
                TextColumn::make('full_name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('العمر')
                    ->sortable(),
                TextColumn::make('gender')
                    ->label('الجنس')
                    ->badge(),
                TextColumn::make('visits_count')
                    ->label('عدد الزيارات')
                    ->counts('visits')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ التسجيل')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'edit' => EditPatient::route('/{record}/edit'),
        ];
    }
}
