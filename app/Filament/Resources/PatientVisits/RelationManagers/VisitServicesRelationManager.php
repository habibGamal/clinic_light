<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Enums\SelectionType;
use App\Enums\VisitServiceStatus;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VisitServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'visitServices';

    protected static ?string $title = 'الخدمات المطلوبة والفحوصات';

    protected array $tempSelectedOptions = [];

    public static function recalculatePrices(Get $get, Set $set): void
    {
        $serviceId = $get('service_id');
        if (!$serviceId) {
            return;
        }

        $service = Service::query()->find($serviceId);
        if (!$service) {
            return;
        }

        $unitPrice = (float) $service->base_price;

        $groups = ServiceOptionGroup::query()->where('service_id', $serviceId)->get();
        foreach ($groups as $group) {
            $selected = $get("selected_options_group_{$group->id}");
            if ($selected) {
                $optionIds = is_array($selected) ? $selected : [$selected];
                $additional = ServiceOption::query()->whereIn('id', $optionIds)->sum('additional_price');
                $unitPrice += (float) $additional;
            }
        }

        $quantity = (int) ($get('quantity') ?? 1);
        $subtotal = $unitPrice * $quantity;
        $discount = (float) ($get('discount_value') ?? 0);
        $total = max(0, $subtotal - $discount);

        $set('unit_price', $unitPrice);
        $set('subtotal', $subtotal);
        $set('total', $total);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اختر الخدمة')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('service_id')
                            ->label('الخدمة')
                            ->options(Service::query()->where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if (!$state) {
                                    $set('unit_price', 0);
                                    $set('subtotal', 0);
                                    $set('total', 0);

                                    return;
                                }

                                $service = Service::query()->find($state);
                                if ($service) {
                                    $set('unit_price', (float) $service->base_price);
                                    $set('quantity', 1);
                                    $set('discount_value', 0);
                                    $set('subtotal', (float) $service->base_price);
                                    $set('total', (float) $service->base_price);

                                    $groups = ServiceOptionGroup::query()->where('service_id', $state)->get();
                                    foreach ($groups as $group) {
                                        $key = "selected_options_group_{$group->id}";
                                        if ($group->selection_type === SelectionType::Single) {
                                            $set($key, null);
                                        } else {
                                            $set($key, []);
                                        }
                                    }
                                }
                            }),

                        Select::make('technician_id')
                            ->label('الفني المنفذ')
                            ->relationship('technician', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('status')
                            ->label('حالة الفحص')
                            ->options(VisitServiceStatus::class)
                            ->default(VisitServiceStatus::Pending)
                            ->required(),
                    ]),
                ]),

            Section::make('خيارات الخدمة (Service Options)')
                ->visible(fn(Get $get): bool => filled($get('service_id')))
                ->schema([
                    Group::make()->schema(function (Get $get, Set $set): array {
                        $serviceId = $get('service_id');
                        if (!$serviceId) {
                            return [];
                        }

                        $groups = ServiceOptionGroup::query()
                            ->where('service_id', $serviceId)
                            ->with('options')
                            ->orderBy('sort_order')
                            ->get();

                        $components = [];
                        foreach ($groups as $group) {
                            $options = $group->options->pluck('name', 'id')->toArray();
                            $key = "selected_options_group_{$group->id}";

                            if ($group->selection_type === SelectionType::Single) {
                                $components[] = Radio::make($key)
                                    ->label($group->name)
                                    ->options($options)
                                    ->required($group->is_required)
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculatePrices($get, $set));
                            } else {
                                if (!is_array($get($key))) {
                                    $set($key, []);
                                }

                                $components[] = CheckboxList::make($key)
                                    ->label($group->name)
                                    ->options($options)
                                    ->default([])
                                    ->live()
                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculatePrices($get, $set));
                            }
                        }

                        return $components;
                    }),
                ]),

            Section::make('الحسابات والخصم')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('quantity')
                            ->label('الكمية')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculatePrices($get, $set)),

                        TextInput::make('unit_price')
                            ->label('سعر الوحدة (شامل الخيارات)')
                            ->numeric()
                            ->readOnly()
                            ->prefix('EGP'),

                        TextInput::make('discount_value')
                            ->label('قيمة الخصم')
                            ->numeric()
                            ->default(0)
                            ->prefix('EGP')
                            ->live()
                            ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculatePrices($get, $set)),

                        TextInput::make('total')
                            ->label('الإجمالي النهائي')
                            ->numeric()
                            ->readOnly()
                            ->prefix('EGP'),
                    ]),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')
                    ->label('اسم الخدمة')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('selectedOptions.serviceOption.name')
                    ->label('الخيارات المختارة')
                    ->badge()
                    ->separator(','),
                TextColumn::make('quantity')
                    ->label('الكمية'),
                TextColumn::make('unit_price')
                    ->label('سعر الوحدة')
                    ->money('EGP'),
                TextColumn::make('discount_value')
                    ->label('الخصم')
                    ->money('EGP'),
                TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('technician.name')
                    ->label('الفني'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('إضافة خدمة جديدة')
                    ->mutateFormDataBeforeCreate(function (array $data): array {
                        $this->tempSelectedOptions = [];
                        foreach ($data as $key => $value) {
                            if (str_starts_with($key, 'selected_options_group_')) {
                                $this->tempSelectedOptions[$key] = $value;
                                unset($data[$key]);
                            }
                        }

                        return $data;
                    })
                    ->after(function (VisitService $record): void {
                        self::saveSelectedOptions($record, $this->tempSelectedOptions);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, VisitService $record): array {
                        $groups = ServiceOptionGroup::query()->where('service_id', $record->service_id)->get();
                        $selectedOptionIds = $record->selectedOptions->pluck('service_option_id')->toArray();

                        foreach ($groups as $group) {
                            $groupOptionIds = ServiceOption::query()->where('option_group_id', $group->id)->pluck('id')->toArray();
                            $intersect = array_values(array_intersect($selectedOptionIds, $groupOptionIds));

                            if ($group->selection_type === SelectionType::Single) {
                                $data["selected_options_group_{$group->id}"] = $intersect[0] ?? null;
                            } else {
                                $data["selected_options_group_{$group->id}"] = $intersect;
                            }
                        }

                        return $data;
                    })
                    ->mutateFormDataBeforeSave(function (array $data): array {
                        $this->tempSelectedOptions = [];
                        foreach ($data as $key => $value) {
                            if (str_starts_with($key, 'selected_options_group_')) {
                                $this->tempSelectedOptions[$key] = $value;
                                unset($data[$key]);
                            }
                        }

                        return $data;
                    })
                    ->after(function (VisitService $record): void {
                        $record->selectedOptions()->delete();
                        self::saveSelectedOptions($record, $this->tempSelectedOptions);
                    }),
                DeleteAction::make(),
            ]);
    }

    private static function saveSelectedOptions(VisitService $record, array $data): void
    {
        $groups = ServiceOptionGroup::query()->where('service_id', $record->service_id)->get();

        foreach ($groups as $group) {
            $key = "selected_options_group_{$group->id}";
            if (isset($data[$key]) && !empty($data[$key])) {
                $optionIds = is_array($data[$key]) ? $data[$key] : [$data[$key]];

                foreach ($optionIds as $optionId) {
                    $option = ServiceOption::query()->find($optionId);
                    if ($option) {
                        VisitServiceSelectedOption::query()->create([
                            'visit_service_id' => $record->id,
                            'service_option_id' => $option->id,
                            'additional_price' => $option->additional_price,
                        ]);
                    }
                }
            }
        }
    }
}
