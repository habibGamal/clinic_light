<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Enums\DiscountType;
use App\Enums\SelectionType;
use App\Enums\VisitServiceStatus;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class VisitServicesRelationManager extends RelationManager
{
    public ?string $pageClass = EditPatientVisit::class;

    protected static string $relationship = 'visitServices';

    protected static ?string $title = 'الخدمات المطلوبة والفحوصات';

    protected array $tempSelectedOptions = [];

    public static function recalculateServicePrices(Get $get, Set $set, int $serviceId): void
    {
        $service = Service::query()->with('optionGroups.options')->find($serviceId);
        if (! $service) {
            return;
        }

        $unitPrice = (float) $service->base_price;

        foreach ($service->optionGroups as $group) {
            $selected = $get("services.{$serviceId}.options.group_{$group->id}");
            if ($selected) {
                $optionIds = is_array($selected) ? $selected : [$selected];
                $additional = ServiceOption::query()->whereIn('id', $optionIds)->sum('additional_price');
                $unitPrice += (float) $additional;
            }
        }

        $quantity = max(1, (int) ($get("services.{$serviceId}.quantity") ?? 1));
        $subtotal = $unitPrice * $quantity;

        $discountType = $get("services.{$serviceId}.discount_type") ?? DiscountType::Fixed;
        if ($discountType instanceof DiscountType) {
            $discountType = $discountType->value;
        }

        $discountVal = (float) ($get("services.{$serviceId}.discount_value") ?? 0);
        $discountAmount = ($discountType === DiscountType::Percent->value || $discountType === 'percent')
            ? ($subtotal * ($discountVal / 100))
            : $discountVal;

        $total = max(0, $subtotal - $discountAmount);

        $set("services.{$serviceId}.unit_price", $unitPrice);
        $set("services.{$serviceId}.subtotal", $subtotal);
        $set("services.{$serviceId}.total", $total);
    }

    public static function recalculatePrices(Get $get, Set $set): void
    {
        $serviceId = $get('service_id');
        if (! $serviceId) {
            return;
        }

        $service = Service::query()->find($serviceId);
        if (! $service) {
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

        $discountType = $get('discount_type') ?? DiscountType::Fixed;
        if ($discountType instanceof DiscountType) {
            $discountType = $discountType->value;
        }

        $discountVal = (float) ($get('discount_value') ?? 0);
        $discountAmount = ($discountType === DiscountType::Percent->value || $discountType === 'percent')
            ? ($subtotal * ($discountVal / 100))
            : $discountVal;

        $total = max(0, $subtotal - $discountAmount);

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
                                if (! $state) {
                                    $set('unit_price', 0);
                                    $set('subtotal', 0);
                                    $set('total', 0);

                                    return;
                                }

                                $service = Service::query()->find($state);
                                if ($service) {
                                    $set('unit_price', (float) $service->base_price);
                                    $set('quantity', 1);
                                    $set('discount_type', DiscountType::Fixed);
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
                            ->hiddenOn('create')
                            ->required(),
                    ]),
                ]),

            Section::make('خيارات الخدمة (Service Options)')
                ->visible(fn (Get $get): bool => filled($get('service_id')))
                ->schema([
                    Group::make()->schema(function (Get $get, Set $set): array {
                        $serviceId = $get('service_id');
                        if (! $serviceId) {
                            return [];
                        }

                        $groups = ServiceOptionGroup::query()
                            ->where('service_id', $serviceId)
                            ->with('options')
                            ->orderBy('sort_order')
                            ->get();

                        $components = [];
                        foreach ($groups as $group) {
                            $options = $group->options->mapWithKeys(function (ServiceOption $opt): array {
                                $priceLabel = (float) $opt->additional_price > 0
                                    ? ' (+'.number_format((float) $opt->additional_price, 2).' EGP)'
                                    : '';

                                return [$opt->id => $opt->name.$priceLabel];
                            })->toArray();

                            $key = "selected_options_group_{$group->id}";

                            if ($group->selection_type === SelectionType::Single) {
                                $components[] = Radio::make($key)
                                    ->label($group->name)
                                    ->options($options)
                                    ->required($group->is_required)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculatePrices($get, $set));
                            } else {
                                if (! is_array($get($key))) {
                                    $set($key, []);
                                }

                                $components[] = CheckboxList::make($key)
                                    ->label($group->name)
                                    ->options($options)
                                    ->default([])
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculatePrices($get, $set));
                            }
                        }

                        return $components;
                    }),
                ]),

            Section::make('الحسابات والخصم')
                ->columnSpanFull()
                ->schema([
                    Grid::make(5)->schema([
                        TextInput::make('quantity')
                            ->label('الكمية')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculatePrices($get, $set)),

                        TextInput::make('unit_price')
                            ->label('سعر الوحدة')
                            ->numeric()
                            ->readOnly()
                            ->prefix('EGP'),

                        Select::make('discount_type')
                            ->label('نوع الخصم')
                            ->options(DiscountType::class)
                            ->default(DiscountType::Fixed)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculatePrices($get, $set)),

                        TextInput::make('discount_value')
                            ->label('قيمة الخصم')
                            ->numeric()
                            ->default(0)
                            ->prefix(fn (Get $get): string => ($get('discount_type') === DiscountType::Percent || $get('discount_type') === 'percent') ? '%' : 'EGP')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculatePrices($get, $set)),

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
                    ->formatStateUsing(function (VisitService $record): string {
                        if ($record->discount_type === DiscountType::Percent) {
                            return "{$record->discount_value}%";
                        }

                        return number_format((float) $record->discount_value, 2).' EGP';
                    }),

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
                    ->modalHeading('اختيار وإضافة خدمات جديدة')
                    ->modalWidth('full')
                    ->form(fn (): array => [
                        Section::make('اختيار الخدمات والخيارات المتاحة')
                            ->columns(3)
                            ->description('اختر الخدمات والخيارات التابعة لها من القائمة أدناه')
                            ->schema(function (Get $get, Set $set): array {
                                $services = Service::query()
                                    ->where('is_active', true)
                                    ->with('optionGroups.options')
                                    ->get();

                                $components = [];
                                foreach ($services as $service) {
                                    $basePrice = number_format((float) $service->base_price, 2);
                                    $serviceComponents = [
                                        Checkbox::make("services.{$service->id}.selected")
                                            ->label("{$service->name} (السعر الأساسي: {$basePrice} EGP)")
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) use ($service): void {
                                                if ($get("services.{$service->id}.selected")) {
                                                    $set("services.{$service->id}.quantity", 1);
                                                    $set("services.{$service->id}.discount_type", DiscountType::Fixed);
                                                    $set("services.{$service->id}.discount_value", 0);
                                                    self::recalculateServicePrices($get, $set, $service->id);
                                                }
                                            }),
                                    ];

                                    if ($service->optionGroups->isNotEmpty()) {
                                        $optionGroupComponents = [];
                                        foreach ($service->optionGroups as $group) {
                                            $options = $group->options->mapWithKeys(function (ServiceOption $opt): array {
                                                $priceLabel = (float) $opt->additional_price > 0
                                                    ? ' (+'.number_format((float) $opt->additional_price, 2).' EGP)'
                                                    : '';

                                                return [$opt->id => $opt->name.$priceLabel];
                                            })->toArray();

                                            $key = "services.{$service->id}.options.group_{$group->id}";

                                            if ($group->selection_type === SelectionType::Single) {
                                                $optionGroupComponents[] = Radio::make($key)
                                                    ->label($group->name)
                                                    ->options($options)
                                                    ->required($group->is_required)
                                                    ->live()
                                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateServicePrices($get, $set, $service->id));
                                            } else {
                                                $optionGroupComponents[] = CheckboxList::make($key)
                                                    ->label($group->name)
                                                    ->options($options)
                                                    ->default([])
                                                    ->live()
                                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateServicePrices($get, $set, $service->id));
                                            }
                                        }

                                        $serviceComponents[] = Group::make()
                                            ->schema($optionGroupComponents)
                                            ->visible(fn (Get $get): bool => (bool) $get("services.{$service->id}.selected"));
                                    }

                                    $components[] = Section::make($service->name)
                                        ->compact()
                                        ->schema($serviceComponents);
                                }

                                return $components;
                            }),

                        Section::make('جدول الخدمات المختارة والتفاصيل المالية')
                            ->schema(function (Get $get): array {
                                $services = Service::query()
                                    ->where('is_active', true)
                                    ->with('optionGroups.options')
                                    ->get();

                                $selectedServiceIds = [];
                                foreach ($services as $service) {
                                    if ($get("services.{$service->id}.selected")) {
                                        $selectedServiceIds[] = $service->id;
                                    }
                                }

                                if (empty($selectedServiceIds)) {
                                    return [
                                        Placeholder::make('no_services_notice')
                                            ->hiddenLabel()
                                            ->content('لم يتم اختيار أي خدمة بعد. يرجى تحديد الخدمات المطلوبة من القائمة أعلاه.'),
                                    ];
                                }

                                $rows = [];
                                // Table Header Row
                                $rows[] = Grid::make(12)->schema([
                                    Placeholder::make('hdr_name')->hiddenLabel()->content('الخدمة والخيارات')->columnSpan(3),
                                    Placeholder::make('hdr_tech')->hiddenLabel()->content('الفني المنفذ')->columnSpan(2),
                                    Placeholder::make('hdr_unit_price')->hiddenLabel()->content('سعر الوحدة')->columnSpan(1),
                                    Placeholder::make('hdr_qty')->hiddenLabel()->content('الكمية')->columnSpan(1),
                                    Placeholder::make('hdr_discount_type')->hiddenLabel()->content('نوع الخصم')->columnSpan(2),
                                    Placeholder::make('hdr_discount_val')->hiddenLabel()->content('قيمة الخصم')->columnSpan(1),
                                    Placeholder::make('hdr_total')->hiddenLabel()->content('الإجمالي')->columnSpan(2),
                                ]);

                                $grandTotal = 0.0;

                                foreach ($services as $service) {
                                    if (! in_array($service->id, $selectedServiceIds, true)) {
                                        continue;
                                    }

                                    $selectedOptionNames = [];
                                    foreach ($service->optionGroups as $group) {
                                        $val = $get("services.{$service->id}.options.group_{$group->id}");
                                        if ($val) {
                                            $optIds = is_array($val) ? $val : [$val];
                                            foreach ($optIds as $optId) {
                                                $opt = $group->options->firstWhere('id', (int) $optId);
                                                if ($opt) {
                                                    $selectedOptionNames[] = $opt->name;
                                                }
                                            }
                                        }
                                    }

                                    $optSummary = ! empty($selectedOptionNames) ? ' ('.implode('، ', $selectedOptionNames).')' : '';
                                    $displayName = $service->name.$optSummary;

                                    $unitPrice = (float) ($get("services.{$service->id}.unit_price") ?? $service->base_price);
                                    $qty = max(1, (int) ($get("services.{$service->id}.quantity") ?? 1));
                                    $discountType = $get("services.{$service->id}.discount_type") ?? DiscountType::Fixed;
                                    if ($discountType instanceof DiscountType) {
                                        $discountType = $discountType->value;
                                    }
                                    $discountVal = (float) ($get("services.{$service->id}.discount_value") ?? 0);
                                    $subtotal = $unitPrice * $qty;
                                    $discountAmount = ($discountType === DiscountType::Percent->value || $discountType === 'percent')
                                        ? ($subtotal * ($discountVal / 100))
                                        : $discountVal;
                                    $rowTotal = (float) ($get("services.{$service->id}.total") ?? max(0, $subtotal - $discountAmount));

                                    $grandTotal += $rowTotal;

                                    $rows[] = Grid::make(12)->schema([
                                        Placeholder::make("services.{$service->id}.name_summary")
                                            ->hiddenLabel()
                                            ->content($displayName)
                                            ->columnSpan(3),

                                        Select::make("services.{$service->id}.technician_id")
                                            ->hiddenLabel()
                                            ->relationship('technician', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(2),

                                        TextInput::make("services.{$service->id}.unit_price")
                                            ->hiddenLabel()
                                            ->prefix('EGP')
                                            ->readOnly()
                                            ->columnSpan(1),

                                        TextInput::make("services.{$service->id}.quantity")
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateServicePrices($get, $set, $service->id))
                                            ->columnSpan(1),

                                        Select::make("services.{$service->id}.discount_type")
                                            ->hiddenLabel()
                                            ->options(DiscountType::class)
                                            ->default(DiscountType::Fixed)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateServicePrices($get, $set, $service->id))
                                            ->columnSpan(2),

                                        TextInput::make("services.{$service->id}.discount_value")
                                            ->hiddenLabel()
                                            ->numeric()
                                            ->default(0)
                                            ->prefix(fn (Get $get): string => ($get("services.{$service->id}.discount_type") === DiscountType::Percent || $get("services.{$service->id}.discount_type") === 'percent') ? '%' : 'EGP')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::recalculateServicePrices($get, $set, $service->id))
                                            ->columnSpan(1),

                                        TextInput::make("services.{$service->id}.total")
                                            ->hiddenLabel()
                                            ->prefix('EGP')
                                            ->readOnly()
                                            ->columnSpan(2),
                                    ]);
                                }

                                // Grand total footer row
                                $rows[] = Grid::make(12)->schema([
                                    Placeholder::make('grand_total_label')
                                        ->hiddenLabel()
                                        ->content('الإجمالي النهائي لجميع الخدمات المختارة:')
                                        ->columnSpan(10),

                                    Placeholder::make('grand_total_val')
                                        ->hiddenLabel()
                                        ->content(number_format($grandTotal, 2).' EGP')
                                        ->columnSpan(2),
                                ]);

                                return $rows;
                            }),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $visit = $livewire->getOwnerRecord();
                        $createdCount = 0;

                        if (! empty($data['services']) && is_array($data['services'])) {
                            foreach ($data['services'] as $serviceId => $sData) {
                                if (empty($sData['selected'])) {
                                    continue;
                                }

                                $service = Service::query()->find($serviceId);
                                if (! $service) {
                                    continue;
                                }

                                $unitPrice = (float) ($sData['unit_price'] ?? $service->base_price);
                                $quantity = max(1, (int) ($sData['quantity'] ?? 1));
                                $subtotal = $unitPrice * $quantity;

                                $discountType = $sData['discount_type'] ?? DiscountType::Fixed->value;
                                if ($discountType instanceof DiscountType) {
                                    $discountType = $discountType->value;
                                }
                                $discountVal = (float) ($sData['discount_value'] ?? 0);
                                $discountAmount = ($discountType === DiscountType::Percent->value || $discountType === 'percent')
                                    ? ($subtotal * ($discountVal / 100))
                                    : $discountVal;

                                $total = max(0, $subtotal - $discountAmount);

                                $visitService = VisitService::query()->create([
                                    'visit_id' => $visit->id,
                                    'service_id' => $serviceId,
                                    'technician_id' => $sData['technician_id'] ?? null,
                                    'quantity' => $quantity,
                                    'unit_price' => $unitPrice,
                                    'discount_type' => $discountType,
                                    'discount_value' => $discountVal,
                                    'subtotal' => $subtotal,
                                    'total' => $total,
                                    'status' => VisitServiceStatus::Pending,
                                ]);

                                $optionsData = $sData['options'] ?? [];
                                foreach ($optionsData as $groupKey => $optVal) {
                                    if (empty($optVal)) {
                                        continue;
                                    }
                                    $optIds = is_array($optVal) ? $optVal : [$optVal];
                                    foreach ($optIds as $optId) {
                                        $opt = ServiceOption::query()->find($optId);
                                        if ($opt) {
                                            VisitServiceSelectedOption::query()->create([
                                                'visit_service_id' => $visitService->id,
                                                'service_option_id' => $opt->id,
                                                'additional_price' => $opt->additional_price,
                                            ]);
                                        }
                                    }
                                }

                                $createdCount++;
                            }
                        } elseif (! empty($data['service_id'])) {
                            $service = Service::query()->find($data['service_id']);
                            if ($service) {
                                $unitPrice = (float) ($data['unit_price'] ?? $service->base_price);
                                $quantity = max(1, (int) ($data['quantity'] ?? 1));
                                $subtotal = $unitPrice * $quantity;

                                $discountType = $data['discount_type'] ?? DiscountType::Fixed->value;
                                if ($discountType instanceof DiscountType) {
                                    $discountType = $discountType->value;
                                }
                                $discountVal = (float) ($data['discount_value'] ?? 0);
                                $discountAmount = ($discountType === DiscountType::Percent->value || $discountType === 'percent')
                                    ? ($subtotal * ($discountVal / 100))
                                    : $discountVal;

                                $total = max(0, $subtotal - $discountAmount);

                                $visitService = VisitService::query()->create([
                                    'visit_id' => $visit->id,
                                    'service_id' => $service->id,
                                    'technician_id' => $data['technician_id'] ?? null,
                                    'quantity' => $quantity,
                                    'unit_price' => $unitPrice,
                                    'discount_type' => $discountType,
                                    'discount_value' => $discountVal,
                                    'subtotal' => $subtotal,
                                    'total' => $total,
                                    'status' => VisitServiceStatus::Pending,
                                ]);

                                foreach ($data as $key => $val) {
                                    if (str_starts_with($key, 'selected_options_group_') && ! empty($val)) {
                                        $optIds = is_array($val) ? $val : [$val];
                                        foreach ($optIds as $optId) {
                                            $opt = ServiceOption::query()->find($optId);
                                            if ($opt) {
                                                VisitServiceSelectedOption::query()->create([
                                                    'visit_service_id' => $visitService->id,
                                                    'service_option_id' => $opt->id,
                                                    'additional_price' => $opt->additional_price,
                                                ]);
                                            }
                                        }
                                    }
                                }

                                $createdCount++;
                            }
                        }

                        if ($createdCount > 0) {
                            app(InvoiceService::class)->syncInvoice($visit);
                            Notification::make()
                                ->title('تمت إضافة الخدمات للزيارة بنجاح')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('writeReport')
                    ->label(fn (?VisitService $record): string => ($record && $record->reports()->exists()) ? 'عرض/تعديل التقرير' : 'كتابة تقرير')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->url(function (?VisitService $record): ?string {
                        if (! $record) {
                            return null;
                        }

                        $firstReport = $record->reports()->first();
                        if ($firstReport) {
                            return \App\Filament\Resources\Reports\ReportResource::getUrl('edit', [
                                'record' => $firstReport,
                                'visit_id' => $record->visit_id,
                            ]);
                        }

                        return \App\Filament\Resources\Reports\ReportResource::getUrl('create', [
                            'visit_id' => $record->visit_id,
                            'visit_service_id' => $record->id,
                        ]);
                    }),

                Action::make('complete')
                    ->label('إكمال')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (VisitService $record): bool => $record->status === VisitServiceStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إكمال الخدمة')
                    ->modalDescription('هل أنت تأكد من تغيير حالة الخدمة إلى مكتمل؟')
                    ->action(function (VisitService $record): void {
                        $record->update(['status' => VisitServiceStatus::Completed]);
                        app(InvoiceService::class)->syncInvoice($record->visit);
                        Notification::make()
                            ->title('تم إكمال الخدمة بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('refundAndCancel')
                    ->label('استرجاع وإلغاء الخدمة')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (VisitService $record): bool => $record->status === VisitServiceStatus::Completed)
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد استرجاع وإلغاء الخدمة')
                    ->modalDescription('هل أنت متاكد من استرجاع وإلغاء هذه الخدمة المكتملة؟ سيتم إضافة مفردات الاسترجاع للفاتورة وإلغاء الخدمة.')
                    ->action(function (VisitService $record): void {
                        app(InvoiceService::class)->refundAndCancelService($record);
                        Notification::make()
                            ->title('تم استرجاع وإلغاء الخدمة بنجاح')
                            ->warning()
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(fn (VisitService $record): bool => in_array($record->status, [VisitServiceStatus::Completed, VisitServiceStatus::Cancelled], true))
                    ->disabled(fn (VisitService $record): bool => in_array($record->status, [VisitServiceStatus::Completed, VisitServiceStatus::Cancelled], true))
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
                    ->mutateFormDataUsing(function (array $data): array {
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
                        app(InvoiceService::class)->syncInvoice($record->visit);
                    }),

                DeleteAction::make()
                    ->hidden(fn (VisitService $record): bool => in_array($record->status, [VisitServiceStatus::Completed, VisitServiceStatus::Cancelled], true))
                    ->disabled(fn (VisitService $record): bool => in_array($record->status, [VisitServiceStatus::Completed, VisitServiceStatus::Cancelled], true))
                    ->before(function (VisitService $record, DeleteAction $action): void {
                        if (in_array($record->status, [VisitServiceStatus::Completed, VisitServiceStatus::Cancelled], true)) {
                            Notification::make()
                                ->title('لا يمكن حذف الخدمة بعد إكمالها أو إلغائها')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->after(function (VisitService $record): void {
                        app(InvoiceService::class)->syncInvoice($record->visit);
                    }),
            ]);
    }

    private static function saveSelectedOptions(VisitService $record, array $data): void
    {
        $groups = ServiceOptionGroup::query()->where('service_id', $record->service_id)->get();

        foreach ($groups as $group) {
            $key = "selected_options_group_{$group->id}";
            if (isset($data[$key]) && ! empty($data[$key])) {
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
