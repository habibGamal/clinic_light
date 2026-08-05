<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Models\VisitService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static ?string $title = 'التقارير الطبية';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('visit_service_id')
                    ->label('خدمة الفحص')
                    ->options(function (RelationManager $livewire): array {
                        return VisitService::query()
                            ->where('visit_id', $livewire->getOwnerRecord()->getKey())
                            ->with('service')
                            ->get()
                            ->pluck('service.name', 'id')
                            ->toArray();
                    })
                    ->required()
                    ->searchable(),

                Select::make('user_id')
                    ->label('الطبيب المعالج')
                    ->relationship('doctor', 'name')
                    ->default(fn () => auth()->id())
                    ->required(),

                TextInput::make('title')
                    ->label('عنوان التقرير')
                    ->required()
                    ->columnSpanFull(),

                RichEditor::make('report_text')
                    ->label('نص التقرير التشخيصي')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان التقرير')
                    ->searchable(),

                TextColumn::make('visitService.service.name')
                    ->label('الخدمة المفحوصة'),

                TextColumn::make('doctor.name')
                    ->label('طبيب التقارير'),

                TextColumn::make('created_at')
                    ->label('تاريخ التقرير')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('إضافة تقرير طبي جديد'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
