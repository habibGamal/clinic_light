<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\RelationManagers;

use App\Filament\Resources\Reports\ReportResource;
use App\Models\Report;
use App\Models\VisitService;
use App\Services\MedicalReportTemplateService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reports';

    protected static ?string $title = 'التقارير الطبية';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
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
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (! $state) {
                                return;
                            }

                            $vs = VisitService::query()->with(['service.serviceCategory'])->find($state);
                            if (! $vs || ! $vs->service) {
                                return;
                            }

                            $currentTitle = (string) ($get('title') ?? '');
                            if (blank($currentTitle) || str_starts_with($currentTitle, 'تقرير طبي')) {
                                $set('title', 'تقرير طبي - '.$vs->service->name);
                            }

                            $currentText = (string) ($get('report_text') ?? '');
                            if (blank($currentText) || ReportResource::isDefaultOrEmptyTemplate($currentText)) {
                                $set('report_text', MedicalReportTemplateService::generateTemplate($vs->service));
                            }
                        }),

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
                        ->resizableImages()
                        ->label('نص التقرير التشخيصي')
                        ->columnSpanFull()
                        ->hintActions([
                            Action::make('applyServiceTemplate')
                                ->label('إعادة تطبيق القالب')
                                ->icon(Heroicon::OutlinedSparkles)
                                ->requiresConfirmation()
                                ->action(function (Set $set, Get $get): void {
                                    $vsId = $get('visit_service_id');
                                    $service = $vsId ? VisitService::query()->with(['service.serviceCategory'])->find($vsId)?->service : null;
                                    $set('report_text', MedicalReportTemplateService::generateTemplate($service));
                                    Notification::make()
                                        ->title('تم تطبيق قالب التقرير بنجاح')
                                        ->success()
                                        ->send();
                                }),
                        ]),
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
                Action::make('create')
                    ->label('إضافة تقرير طبي جديد')
                    ->icon(Heroicon::OutlinedPlus)
                    ->button()
                    ->url(fn (RelationManager $livewire): string => ReportResource::getUrl('create', [
                        'visit_id' => $livewire->getOwnerRecord()->getKey(),
                    ])),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('تعديل التقرير')
                    ->url(fn (Report $record, RelationManager $livewire): string => ReportResource::getUrl('edit', [
                        'record' => $record,
                        'visit_id' => $livewire->getOwnerRecord()->getKey(),
                    ])),
                DeleteAction::make(),
            ]);
    }
}
