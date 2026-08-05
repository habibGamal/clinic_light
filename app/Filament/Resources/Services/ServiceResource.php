<?php

declare(strict_types=1);

namespace App\Filament\Resources\Services;

use App\Enums\SelectionType;
use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $parentResource = ServiceCategoryResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'الخدمات';

    protected static ?string $modelLabel = 'خدمة';

    protected static ?string $pluralModelLabel = 'الخدمات';

    protected static string|UnitEnum|null $navigationGroup = 'إدارة الخدمات';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('تفاصيل الخدمة')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('category_id')
                            ->label('التصنيف')
                            ->relationship('serviceCategory', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->label('اسم الخدمة')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('الكود')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        TextInput::make('base_price')
                            ->label('السعر الأساسي')
                            ->numeric()
                            ->required()
                            ->prefix('EGP'),
                        TextInput::make('cost')
                            ->label('التكلفة')
                            ->numeric()
                            ->required()
                            ->prefix('EGP'),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                    ]),
                ]),

            Section::make('مجموعات الخيارات (Option Groups)')
                ->description('إدارة خيارات الخدمة وأسعارها الإضافية مباشرة')
                ->schema([
                    Repeater::make('optionGroups')
                        ->relationship('optionGroups')
                        ->label('مجموعة خيارات')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('name')
                                    ->label('اسم المجموعة')
                                    ->placeholder('مثال: الغرض، المنطقة')
                                    ->required(),
                                Select::make('selection_type')
                                    ->label('نوع الاختيار')
                                    ->options(SelectionType::class)
                                    ->required()
                                    ->default(SelectionType::Single),
                                Checkbox::make('is_required')
                                    ->label('مطلوب؟')
                                    ->default(false),
                            ]),

                            Repeater::make('options')
                                ->relationship('options')
                                ->label('الخيارات الفرعية')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('name')
                                            ->label('اسم الخيار')
                                            ->placeholder('مثال: زراعة، فك كامل')
                                            ->required(),
                                        TextInput::make('additional_price')
                                            ->label('السعر الإضافي')
                                            ->numeric()
                                            ->default(0)
                                            ->prefix('EGP')
                                            ->required(),
                                        Checkbox::make('is_default')
                                            ->label('محدد افتراضياً؟')
                                            ->default(false),
                                    ]),
                                ])
                                ->columns(1)
                                ->collapsible()
                                ->defaultItems(1),
                        ])
                        ->columns(1)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الخدمة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serviceCategory.name')
                    ->label('التصنيف')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->label('السعر الأساسي')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('التكلفة')
                    ->money('EGP')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
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
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
