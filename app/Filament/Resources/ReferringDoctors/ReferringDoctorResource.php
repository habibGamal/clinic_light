<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferringDoctors;

use App\Filament\Resources\ReferringDoctors\Pages\CreateReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\EditReferringDoctor;
use App\Filament\Resources\ReferringDoctors\Pages\ListReferringDoctors;
use App\Models\ReferringDoctor;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class ReferringDoctorResource extends Resource
{
    protected static ?string $model = ReferringDoctor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'أطباء الإحالة';

    protected static ?string $modelLabel = 'طبيب إحالة';

    protected static ?string $pluralModelLabel = 'أطباء الإحالة';

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المرضى';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات طبيب الإحالة')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('specialization')
                            ->label('التخصص')
                            ->maxLength(255),
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
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('specialization')
                    ->label('التخصص')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('patient_visits_count')
                    ->label('عدد الإحالات')
                    ->counts('patientVisits')
                    ->sortable(),
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
            'index' => ListReferringDoctors::route('/'),
            'create' => CreateReferringDoctor::route('/create'),
            'edit' => EditReferringDoctor::route('/{record}/edit'),
        ];
    }
}
