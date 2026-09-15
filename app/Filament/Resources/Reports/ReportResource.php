<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use BackedEnum;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'التقارير الطبية';

    protected static ?string $modelLabel = 'تقرير طبي';

    protected static ?string $pluralModelLabel = 'التقارير الطبية';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل التقرير')
                ->columnSpanFull()
                ->schema([
                    Section::make()
                        ->columnSpanFull()
                        ->schema([
                            Select::make('visit_service_id')
                                ->label('خدمة الزيارة')
                                ->relationship('visitService', 'id')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "زيارة #{$record->visit_id} - {$record->service?->name}")
                                ->required()
                                ->searchable()
                                ->preload(),
                            Select::make('user_id')
                                ->label('الطبيب المعالج')
                                ->relationship('doctor', 'name')
                                ->default(fn () => auth()->id())
                                ->required()
                                ->searchable()
                                ->preload(),
                            TextInput::make('title')
                                ->label('عنوان التقرير')
                                ->required()
                                ->columnSpanFull()
                                ->maxLength(255),
                            RichEditor::make('report_text')
                                ->label('نص التقرير')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitService.visit.patient.full_name')
                    ->label('المريض')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('visitService.service.name')
                    ->label('الخدمة')
                    ->sortable(),
                TextColumn::make('doctor.name')
                    ->label('الطبيب')
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
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }
}
