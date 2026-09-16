<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReportTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ReportTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('معلومات قالب التقرير')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('اسم القالب')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),

                            Select::make('service_id')
                                ->label('مخصص لخدمة محددة (اختياري)')
                                ->relationship('service', 'name')
                                ->searchable()
                                ->preload()
                                ->placeholder('قالب عام يمكن استخدامه مع أي خدمة')
                                ->columnSpan(1),

                            Toggle::make('is_active')
                                ->label('مفعل للاستخدام')
                                ->default(true)
                                ->inline(false)
                                ->columnSpan(1),
                        ]),

                        RichEditor::make('content')
                            ->resizableImages()
                            ->label('نص وهيكل القالب الطبي')
                            ->required()
                            ->helperText('يمكنك كتابة {service_name} ليتم استبداله تلقائياً باسم الخدمة عند استخدام القالب، واستخدام [ ... ] لخانات ملء الفراغات للطبيب.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
