<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Filters\ShiftFilter;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\PatientVisit;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\View\View;

final class ShiftVisitsTableWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'جدول زيارات المرضى للورديات المحددة';

    public function table(Table $table): Table
    {
        $shiftIds = $this->getSelectedShiftIds();

        return $table
            ->queryStringIdentifier('shiftVisits')
            ->query(
                ShiftFilter::applyToQuery(
                    PatientVisit::query()
                        ->with(['patient', 'referringDoctor', 'invoice', 'shift.user', 'visitServices.service', 'payments'])
                        ->latest('visit_date'),
                    ['mode' => 'shifts', 'shift_ids' => $shiftIds]
                )
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('patient.full_name')
                    ->label('اسم المريض')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('patient.phone')
                    ->label('رقم الهاتف')
                    ->searchable()
                    ->copyable()
                    ->icon(Heroicon::Phone),

                TextColumn::make('visit_date')
                    ->label('تاريخ وتوقيت الزيارة')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),

                TextColumn::make('referringDoctor.name')
                    ->label('طبيب الإحالة')
                    ->placeholder('مباشر (بدون إحالة)')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('حالة الزيارة')
                    ->badge(),

                TextColumn::make('invoice.total_amount')
                    ->label('إجمالي الفاتورة')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('invoice.paid_amount')
                    ->label('المدفوع')
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('invoice.remaining_amount')
                    ->label('المتبقي المستحق')
                    ->money('EGP')
                    ->color(fn (?PatientVisit $record): string => ($record?->invoice?->remaining_amount ?? 0) > 0 ? 'danger' : 'success'),
            ])
            ->defaultSort('visit_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الزيارة')
                    ->options(VisitStatus::class),

                SelectFilter::make('referring_doctor_id')
                    ->label('طبيب الإحالة')
                    ->relationship('referringDoctor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('viewDetails')
                    ->label('عرض التفاصيل')
                    ->icon(Heroicon::Eye)
                    ->color('info')
                    ->modalHeading(fn (PatientVisit $record): string => "تفاصيل الزيارة #{$record->id} - {$record->patient?->full_name}")
                    ->modalWidth('5xl')
                    ->modalContent(fn (PatientVisit $record): View => view('filament.pages.reports.visit-details-modal', [
                        'visit' => $record->loadMissing(['patient', 'referringDoctor', 'invoice.items', 'payments', 'visitServices.service', 'shift.user']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Action::make('edit')
                    ->label('تعديل')
                    ->icon(Heroicon::PencilSquare)
                    ->color('gray')
                    ->url(fn (PatientVisit $record): string => PatientVisitResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting && Shift::query()->where('status', ShiftStatus::Open)->exists()),
            ])
            ->emptyStateHeading('لا توجد زيارات مسجلة')
            ->emptyStateDescription(empty($shiftIds) ? 'يرجى تحديد وردية واحدة على الأقل لعرض زياراتها.' : 'لم يتم تسجيل أي زيارات مرضى في هذه الوردية.')
            ->emptyStateIcon(Heroicon::ClipboardDocumentList);
    }
}
