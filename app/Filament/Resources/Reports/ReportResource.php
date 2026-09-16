<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\Models\VisitService;
use App\Services\MedicalReportTemplateService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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
            Section::make('تفاصيل التقرير الطبي')
                ->columnSpanFull()
                ->schema([
                    Section::make()
                        ->columnSpanFull()
                        ->schema([
                            Select::make('visit_service_id')
                                ->label('خدمة الفحص / الزيارة')
                                ->options(function (?Model $record): array {
                                    $visitId = request()->query('visit_id') ?? $record?->visitService?->visit_id;
                                    $query = VisitService::query()->with(['service.serviceCategory', 'visit.patient']);

                                    if ($visitId) {
                                        return $query->where('visit_id', $visitId)
                                            ->get()
                                            ->mapWithKeys(fn (VisitService $vs) => [
                                                $vs->id => ($vs->service?->name ?? "خدمة #{$vs->id}").($vs->service?->code ? " ({$vs->service->code})" : ''),
                                            ])
                                            ->toArray();
                                    }

                                    return $query->latest('id')->take(50)->get()
                                        ->mapWithKeys(fn (VisitService $vs) => [
                                            $vs->id => "زيارة #{$vs->visit_id} - ".($vs->visit?->patient?->full_name ?? 'مريض').' - '.($vs->service?->name ?? "خدمة #{$vs->id}"),
                                        ])
                                        ->toArray();
                                })
                                ->getSearchResultsUsing(function (string $search, ?Model $record): array {
                                    $visitId = request()->query('visit_id') ?? $record?->visitService?->visit_id;
                                    $query = VisitService::query()->with(['service.serviceCategory', 'visit.patient']);

                                    if ($visitId) {
                                        $query->where('visit_id', $visitId);
                                    }

                                    return $query->where(function ($q) use ($search): void {
                                        $q->whereHas('service', fn ($sq) => $sq->where('name', 'like', "%{$search}%"))
                                            ->orWhereHas('visit.patient', fn ($pq) => $pq->where('full_name', 'like', "%{$search}%"));
                                    })
                                        ->take(50)
                                        ->get()
                                        ->mapWithKeys(fn (VisitService $vs) => [
                                            $vs->id => "زيارة #{$vs->visit_id} - ".($vs->visit?->patient?->full_name ?? 'مريض').' - '.($vs->service?->name ?? "خدمة #{$vs->id}"),
                                        ])
                                        ->toArray();
                                })
                                ->default(function (): ?int {
                                    if ($vsId = request()->query('visit_service_id')) {
                                        return (int) $vsId;
                                    }

                                    if ($visitId = request()->query('visit_id')) {
                                        return VisitService::query()->where('visit_id', $visitId)->first()?->id;
                                    }

                                    return null;
                                })
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $visitService = VisitService::query()->with(['service.serviceCategory', 'visit.patient'])->find($state);
                                    if (! $visitService || ! $visitService->service) {
                                        return;
                                    }

                                    $service = $visitService->service;
                                    $currentTitle = (string) ($get('title') ?? '');

                                    if (blank($currentTitle) || str_starts_with($currentTitle, 'تقرير طبي') || str_starts_with($currentTitle, 'تقرير فحص')) {
                                        $set('title', 'تقرير طبي - '.$service->name);
                                    }

                                    $currentText = (string) ($get('report_text') ?? '');
                                    if (blank($currentText) || self::isDefaultOrEmptyTemplate($currentText)) {
                                        $set('report_text', MedicalReportTemplateService::generateTemplate($service));
                                    }
                                }),

                            Select::make('user_id')
                                ->label('الطبيب المعالج / كاتب التقرير')
                                ->relationship('doctor', 'name')
                                ->default(fn () => auth()->id())
                                ->required()
                                ->searchable()
                                ->preload(),

                            TextInput::make('title')
                                ->label('عنوان التقرير')
                                ->default(function (): ?string {
                                    $vsId = request()->query('visit_service_id');
                                    if (! $vsId && ($visitId = request()->query('visit_id'))) {
                                        $vsId = VisitService::query()->where('visit_id', $visitId)->first()?->id;
                                    }

                                    if ($vsId) {
                                        $vs = VisitService::query()->with('service')->find($vsId);
                                        if ($vs?->service) {
                                            return 'تقرير طبي - '.$vs->service->name;
                                        }
                                    }

                                    return null;
                                })
                                ->required()
                                ->columnSpanFull()
                                ->maxLength(255),

                            RichEditor::make('report_text')
                                ->resizableImages()
                                ->label('نص التقرير التشخيصي (املأ الفراغات بالنتائج)')
                                ->default(function (): string {
                                    $vsId = request()->query('visit_service_id');
                                    if (! $vsId && ($visitId = request()->query('visit_id'))) {
                                        $vsId = VisitService::query()->where('visit_id', $visitId)->first()?->id;
                                    }

                                    $service = null;
                                    if ($vsId) {
                                        $service = VisitService::query()->with(['service.serviceCategory'])->find($vsId)?->service;
                                    }

                                    return MedicalReportTemplateService::generateTemplate($service);
                                })
                                ->columnSpanFull()
                                ->hintActions([
                                    Action::make('applyServiceTemplate')
                                        ->label('تطبيق قالب الخدمة')
                                        ->icon(Heroicon::OutlinedSparkles)
                                        ->color('primary')
                                        ->requiresConfirmation()
                                        ->modalHeading('تأكيد إعادة تطبيق القالب الطبي')
                                        ->modalDescription('سيؤدي هذا الإجراء إلى توليد القالب الجاهز الخاص بالخدمة الحالية واستبدال النص الحالي. هل أنت متأكد؟')
                                        ->action(function (Set $set, Get $get): void {
                                            $vsId = $get('visit_service_id');
                                            $service = $vsId ? VisitService::query()->with(['service.serviceCategory'])->find($vsId)?->service : null;
                                            $set('report_text', MedicalReportTemplateService::generateTemplate($service));
                                            Notification::make()
                                                ->title('تم تطبيق قالب التقرير بنجاح')
                                                ->success()
                                                ->send();
                                        }),

                                    Action::make('chooseTemplate')
                                        ->label('اختيار من القوالب')
                                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                                        ->color('gray')
                                        ->form([
                                            Select::make('template_id')
                                                ->label('اختر القالب المطلوب')
                                                ->options(function (Get $get): array {
                                                    $vsId = $get('visit_service_id');
                                                    $serviceId = $vsId ? VisitService::find($vsId)?->service_id : null;
                                                    $userTemplates = MedicalReportTemplateService::getSavedTemplatesOptions($serviceId);
                                                    $builtIn = MedicalReportTemplateService::getTemplateTypes();

                                                    $options = [];
                                                    if (! empty($userTemplates)) {
                                                        $options['قوالب مخصصة محفوظة'] = $userTemplates;
                                                    }
                                                    $options['القوالب القياسية المدمجة'] = $builtIn;

                                                    return $options;
                                                })
                                                ->required()
                                                ->searchable(),
                                        ])
                                        ->action(function (array $data, Set $set, Get $get): void {
                                            $vsId = $get('visit_service_id');
                                            $service = $vsId ? VisitService::query()->with(['service.serviceCategory'])->find($vsId)?->service : null;
                                            $set('report_text', MedicalReportTemplateService::generateTemplate($service, (string) $data['template_id']));
                                            Notification::make()
                                                ->title('تم تطبيق القالب المختار بنجاح')
                                                ->success()
                                                ->send();
                                        }),

                                    Action::make('saveAsTemplate')
                                        ->label('حفظ كقالب جديد')
                                        ->icon(Heroicon::OutlinedBookmark)
                                        ->color('success')
                                        ->form([
                                            TextInput::make('name')
                                                ->label('اسم القالب الجديد')
                                                ->required()
                                                ->maxLength(255),
                                            Select::make('service_id')
                                                ->label('تخصيص القالب لخدمة محددة (اختياري)')
                                                ->options(fn () => \App\Models\Service::query()->where('is_active', true)->pluck('name', 'id'))
                                                ->default(function (Get $get): ?int {
                                                    $vsId = $get('visit_service_id');

                                                    return $vsId ? VisitService::find($vsId)?->service_id : null;
                                                })
                                                ->placeholder('قالب عام لكافة الخدمات')
                                                ->searchable(),
                                        ])
                                        ->action(function (array $data, Get $get): void {
                                            $content = (string) ($get('report_text') ?? '');
                                            if (blank(strip_tags($content))) {
                                                Notification::make()
                                                    ->title('لا يمكن حفظ قالب فارغ')
                                                    ->danger()
                                                    ->send();

                                                return;
                                            }

                                            \App\Models\ReportTemplate::create([
                                                'name' => $data['name'],
                                                'service_id' => $data['service_id'] ?? null,
                                                'content' => $content,
                                                'is_active' => true,
                                                'user_id' => auth()->id(),
                                            ]);

                                            Notification::make()
                                                ->title('تم حفظ القالب بنجاح وإتاحته للاستخدام')
                                                ->success()
                                                ->send();
                                        }),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function isDefaultOrEmptyTemplate(string $text): bool
    {
        $stripped = mb_trim(strip_tags($text));
        if (blank($stripped)) {
            return true;
        }

        return str_contains($text, 'تقرير فحص') ||
            str_contains($text, 'تقرير المسح') ||
            str_contains($text, 'تقرير طبي تشخيصي');
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
