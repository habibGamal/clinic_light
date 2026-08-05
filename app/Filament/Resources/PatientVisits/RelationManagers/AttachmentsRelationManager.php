<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Models\VisitService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = 'الملفات والأشعة والملحقات';

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

                TextInput::make('file_name')
                    ->label('اسم الملف')
                    ->required(),

                FileUpload::make('file_path')
                    ->label('تحميل الملف (DICOM, PDF, JPG, PNG, STL)')
                    ->directory('attachments')
                    ->visibility('public')
                    ->required()
                    ->columnSpanFull()
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if ($state && is_object($state) && method_exists($state, 'getClientOriginalName')) {
                            $set('file_name', $state->getClientOriginalName());
                        }
                    }),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('اسم الملف')
                    ->searchable(),

                TextColumn::make('visitService.service.name')
                    ->label('الخدمة المرتبطة'),

                TextColumn::make('uploader.name')
                    ->label('تم الرفع بواسطة'),

                TextColumn::make('created_at')
                    ->label('تاريخ الرفع')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('رفع ملف جديد')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_by'] = auth()->id();
                        $data['created_at'] = now();

                        return $data;
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
