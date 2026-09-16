<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits;

use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\Pages\CreatePatientVisit;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\Pages\ListPatientVisits;
use App\Filament\Resources\PatientVisits\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\ReportsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\VisitServicesRelationManager;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class PatientVisitResource extends Resource
{
    protected static ?string $model = PatientVisit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'زيارات المرضى';

    protected static ?string $modelLabel = 'زيارة مريض';

    protected static ?string $pluralModelLabel = 'زيارات المرضى';

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المرضى';

    public static function canEdit(Model $record): bool
    {
        /** @var PatientVisit $record */
        return $record->status === VisitStatus::Waiting;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('معلومات الزيارة الأساسية')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('patient_id')
                            ->label('المريض')
                            ->relationship('patient', 'full_name')
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => "{$record->full_name}".($record->phone ? " ({$record->phone})" : ''))
                            ->required()
                            ->searchable(['full_name', 'phone'])
                            ->preload()
                            ->createOptionForm([
                                Grid::make(2)->schema([
                                    TextInput::make('full_name')
                                        ->label('الاسم الكامل')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('phone')
                                        ->label('الهاتف')
                                        ->tel()
                                        ->unique(Patient::class, 'phone')
                                        ->maxLength(20),
                                    TextInput::make('age')
                                        ->label('العمر')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(150),
                                    Select::make('gender')
                                        ->label('الجنس')
                                        ->options(\App\Enums\Gender::class),
                                    Textarea::make('address')
                                        ->label('العنوان')
                                        ->columnSpanFull(),
                                    Textarea::make('notes')
                                        ->label('ملاحظات')
                                        ->columnSpanFull(),
                                ]),
                            ]),

                        Select::make('referring_doctor_id')
                            ->label('طبيب الإحالة (خارجي)')
                            ->relationship('referringDoctor', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('shift_id')
                            ->label('الوردية')
                            ->relationship('shift', 'id')
                            ->getOptionLabelFromRecordUsing(fn (Shift $record) => "وردية #{$record->id} ({$record->user?->name}) - {$record->status->value}")
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(false)
                            ->hiddenOn('create'),

                        DateTimePicker::make('visit_date')
                            ->label('تاريخ وتوقيت الزيارة')
                            ->default(now())
                            ->required(),

                        Textarea::make('notes')
                            ->label('ملاحظات الزيارة')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('رقم الزيارة')
                    ->sortable(),

                TextColumn::make('patient.full_name')
                    ->label('المريض')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('patient.phone')
                    ->label('هاتف المريض')
                    ->searchable(),

                TextColumn::make('referringDoctor.name')
                    ->label('طبيب الإحالة')
                    ->placeholder('مباشر'),

                TextColumn::make('visit_date')
                    ->label('تاريخ الزيارة')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),

                TextColumn::make('visit_services_count')
                    ->label('عدد الفحوصات')
                    ->counts('visitServices'),
            ])
            ->defaultSort('visit_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(VisitStatus::class),
            ])
            ->recordActions([
                Action::make('viewInvoice')
                    ->label('عرض الفاتورة')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->modalHeading(fn (PatientVisit $record): string => "فاتورة الزيارة #{$record->id}")
                    ->modalWidth('5xl')
                    ->modalContent(function (PatientVisit $record): View {
                        $invoice = app(\App\Services\InvoiceService::class)->syncInvoice($record);
                        $record->load(['patient', 'referringDoctor', 'invoice.items', 'payments']);

                        return view('filament.patient-visits.invoice-modal', [
                            'visit' => $record,
                            'invoice' => $invoice,
                            'invoiceTotal' => (float) $invoice->total_amount,
                            'totalPaid' => (float) $invoice->paid_amount,
                            'remainingDue' => (float) $invoice->remaining_amount,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Action::make('complete')
                    ->label('إكمال الزيارة')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting)
                    ->requiresConfirmation()
                    ->action(function (PatientVisit $record): void {
                        if ($record->hasDuePayments()) {
                            Notification::make()
                                ->title('لا يمكن إكمال الزيارة')
                                ->body('توجد مبالغ مستحقة على هذه الزيارة بقيمة '.number_format($record->duePaymentAmount(), 2).' EGP. يرجى سداد المبلغ أولاً.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => VisitStatus::Completed]);
                        Notification::make()
                            ->title('تم إكمال الزيارة بنجاح')
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('إلغاء الزيارة')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting)
                    ->requiresConfirmation()
                    ->action(function (PatientVisit $record): void {
                        $record->update(['status' => VisitStatus::Cancelled]);
                        Notification::make()
                            ->title('تم إلغاء الزيارة بنجاح')
                            ->warning()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            'visitServices' => VisitServicesRelationManager::class,
            'payments' => PaymentsRelationManager::class,
            'reports' => ReportsRelationManager::class,
            'attachments' => AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientVisits::route('/'),
            'create' => CreatePatientVisit::route('/create'),
            'edit' => EditPatientVisit::route('/{record}/edit'),
        ];
    }
}
