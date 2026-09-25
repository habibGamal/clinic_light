<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\PatientVisit;
use App\Models\Shift;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\View\View;

final class DoctorReferralVisitsTableWidget extends BaseWidget
{
    public int $doctorId;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'سجل زيارات المرضى المحالين';

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('doctorVisits')
            ->query(
                PatientVisit::query()
                    ->where('referring_doctor_id', $this->doctorId)
                    ->when(! empty($this->shiftIds), fn ($q) => $q->whereIn('shift_id', $this->shiftIds))
                    ->with(['patient', 'referringDoctor', 'invoice', 'shift.user', 'visitServices.service', 'payments'])
                    ->latest('visit_date')
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

                TextColumn::make('shift_id')
                    ->label('الوردية')
                    ->state(fn (PatientVisit $record): string => $record->shift ? "وردية #{$record->shift->id}" : '-')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('حالة الزيارة')
                    ->badge(),

                TextColumn::make('invoice.total_amount')
                    ->label('إجمالي الفاتورة')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->state(function (PatientVisit $record): float {
                        return (float) $record->payments
                            ->filter(fn ($p): bool => ($p->type ?? 'payment') !== 'refund' && (float) $p->amount > 0)
                            ->sum('amount');
                    })
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('refunded_amount')
                    ->label('المسترد')
                    ->state(function (PatientVisit $record): float {
                        return abs((float) $record->payments
                            ->filter(fn ($p): bool => $p->type === 'refund' || (float) $p->amount < 0)
                            ->sum('amount'));
                    })
                    ->money('EGP')
                    ->color('danger'),

                TextColumn::make('net_revenue')
                    ->label('صافي الإيراد')
                    ->state(function (PatientVisit $record): float {
                        $paid = (float) $record->payments
                            ->filter(fn ($p): bool => ($p->type ?? 'payment') !== 'refund' && (float) $p->amount > 0)
                            ->sum('amount');
                        $refunded = abs((float) $record->payments
                            ->filter(fn ($p): bool => $p->type === 'refund' || (float) $p->amount < 0)
                            ->sum('amount'));

                        return $paid - $refunded;
                    })
                    ->money('EGP')
                    ->weight('bold')
                    ->color(function (PatientVisit $record): string {
                        $paid = (float) $record->payments
                            ->filter(fn ($p): bool => ($p->type ?? 'payment') !== 'refund' && (float) $p->amount > 0)
                            ->sum('amount');
                        $refunded = abs((float) $record->payments
                            ->filter(fn ($p): bool => $p->type === 'refund' || (float) $p->amount < 0)
                            ->sum('amount'));

                        return ($paid - $refunded) >= 0 ? 'success' : 'danger';
                    }),

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
            ->emptyStateHeading('لا توجد زيارات مسجلة لهذا الطبيب')
            ->emptyStateDescription(! empty($this->shiftIds) ? 'لم يتم تسجيل أي زيارات محالة في الورديات المحددة.' : 'لم يتم تسجيل أي زيارات محالة لهذا الطبيب بعد.')
            ->emptyStateIcon(Heroicon::ClipboardDocumentList);
    }
}
