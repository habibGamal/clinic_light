<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReportTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم القالب')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('الخدمة المخصصة')
                    ->placeholder('قالب عام لكافة الخدمات')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('مفعل')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
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
}
