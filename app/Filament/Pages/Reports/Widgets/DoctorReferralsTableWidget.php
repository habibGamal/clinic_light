<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Filament\Pages\Reports\DoctorReferralDetailsReport;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\ReferringDoctor;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

final class DoctorReferralsTableWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'إحصائيات إحالات الأطباء التفصيلية';

    /**
     * @var array<int, float>|null
     */
    protected ?array $cachedPayments = null;

    /**
     * @var array<int, float>|null
     */
    protected ?array $cachedRefunds = null;

    /**
     * @var array<int, int>|null
     */
    protected ?array $cachedNewPatients = null;

    protected ?int $cachedTotalReferrals = null;

    public function table(Table $table): Table
    {
        $shiftIds = $this->getSelectedShiftIds();

        return $table
            ->queryStringIdentifier('doctorReferrals')
            ->query(
                ReferringDoctor::query()
                    ->when(
                        ! empty($shiftIds),
                        fn ($q) => $q->whereHas('patientVisits', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)),
                        fn ($q) => $q->whereRaw('1 = 0')
                    )
                    ->withCount([
                        'patientVisits as referrals_count' => fn ($q) => $q->whereIn('shift_id', $shiftIds),
                        'patientVisits as completed_visits_count' => fn ($q) => $q->whereIn('shift_id', $shiftIds)->where('status', VisitStatus::Completed),
                        'patientVisits as waiting_visits_count' => fn ($q) => $q->whereIn('shift_id', $shiftIds)->where('status', VisitStatus::Waiting),
                        'patientVisits as cancelled_visits_count' => fn ($q) => $q->whereIn('shift_id', $shiftIds)->where('status', VisitStatus::Cancelled),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الطبيب')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (ReferringDoctor $record): ?string => $record->specialization ? "التخصص: {$record->specialization}" : null),

                TextColumn::make('referrals_count')
                    ->label('عدد الإحالات')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('completed_visits_count')
                    ->label('الزيارات المكتملة')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('waiting_visits_count')
                    ->label('زيارات في الانتظار')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('cancelled_visits_count')
                    ->label('الزيارات الملغاة')
                    ->sortable()
                    ->badge()
                    ->color('danger'),

                TextColumn::make('new_referrals_count')
                    ->label('إحالات جديدة')
                    ->state(fn (ReferringDoctor $record): int => $this->getNewPatientsForDoctor((int) $record->id))
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_payments')
                    ->label('إجمالي المدفوعات')
                    ->state(fn (ReferringDoctor $record): float => $this->getPaymentsForDoctor((int) $record->id))
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('total_refunds')
                    ->label('إجمالي المستردات')
                    ->state(fn (ReferringDoctor $record): float => $this->getRefundsForDoctor((int) $record->id))
                    ->money('EGP')
                    ->color('danger'),

                TextColumn::make('net_revenue')
                    ->label('صافي الإيرادات')
                    ->state(fn (ReferringDoctor $record): float => $this->getPaymentsForDoctor((int) $record->id) - $this->getRefundsForDoctor((int) $record->id))
                    ->money('EGP')
                    ->weight('bold')
                    ->color(fn (ReferringDoctor $record): string => ($this->getPaymentsForDoctor((int) $record->id) - $this->getRefundsForDoctor((int) $record->id)) >= 0 ? 'success' : 'danger'),

                TextColumn::make('percent_of_referrals')
                    ->label('نسبة الإحالات')
                    ->state(function (ReferringDoctor $record): string {
                        $total = $this->getTotalReferralsCombined();
                        if ($total <= 0) {
                            return '0%';
                        }
                        $count = (int) ($record->referrals_count ?? 0);
                        $percentage = round(($count / $total) * 100, 1);

                        return "{$percentage}%";
                    })
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('referrals_count', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('عرض التفاصيل')
                    ->icon(Heroicon::Eye)
                    ->color('primary')
                    ->url(fn (ReferringDoctor $record): string => DoctorReferralDetailsReport::getUrl([
                        'record' => $record->id,
                        'shift_ids' => $shiftIds,
                    ])),
            ])
            ->emptyStateHeading('لا توجد إحالات مسجلة للأطباء')
            ->emptyStateDescription(
                empty($shiftIds)
                    ? 'يرجى تحديد وردية واحدة على الأقل لعرض بيانات الإحالات.'
                    : 'لم يتم تسجيل أي زيارات محالة في هذه الورديات.'
            )
            ->emptyStateIcon(Heroicon::UserPlus);
    }

    protected function getPaymentsForDoctor(int $doctorId): float
    {
        if ($this->cachedPayments === null) {
            $shiftIds = $this->getSelectedShiftIds();
            if (empty($shiftIds)) {
                $this->cachedPayments = [];
            } else {
                $this->cachedPayments = Payment::query()
                    ->join('patient_visits', 'payments.visit_id', '=', 'patient_visits.id')
                    ->whereIn('patient_visits.shift_id', $shiftIds)
                    ->where(function ($q): void {
                        $q->where('payments.type', '!=', 'refund')->orWhereNull('payments.type');
                    })
                    ->where('payments.amount', '>', 0)
                    ->groupBy('patient_visits.referring_doctor_id')
                    ->selectRaw('patient_visits.referring_doctor_id, sum(payments.amount) as total')
                    ->pluck('total', 'referring_doctor_id')
                    ->map(fn ($val): float => (float) $val)
                    ->all();
            }
        }

        return $this->cachedPayments[$doctorId] ?? 0.0;
    }

    protected function getRefundsForDoctor(int $doctorId): float
    {
        if ($this->cachedRefunds === null) {
            $shiftIds = $this->getSelectedShiftIds();
            if (empty($shiftIds)) {
                $this->cachedRefunds = [];
            } else {
                $this->cachedRefunds = Payment::query()
                    ->join('patient_visits', 'payments.visit_id', '=', 'patient_visits.id')
                    ->whereIn('patient_visits.shift_id', $shiftIds)
                    ->where(function ($q): void {
                        $q->where('payments.type', 'refund')->orWhere('payments.amount', '<', 0);
                    })
                    ->groupBy('patient_visits.referring_doctor_id')
                    ->selectRaw('patient_visits.referring_doctor_id, sum(abs(payments.amount)) as total')
                    ->pluck('total', 'referring_doctor_id')
                    ->map(fn ($val): float => (float) $val)
                    ->all();
            }
        }

        return $this->cachedRefunds[$doctorId] ?? 0.0;
    }

    protected function getNewPatientsForDoctor(int $doctorId): int
    {
        if ($this->cachedNewPatients === null) {
            $shiftIds = $this->getSelectedShiftIds();
            if (empty($shiftIds)) {
                $this->cachedNewPatients = [];
            } else {
                $doctorPatientPairs = PatientVisit::query()
                    ->whereIn('shift_id', $shiftIds)
                    ->whereNotNull('referring_doctor_id')
                    ->select('referring_doctor_id', 'patient_id')
                    ->distinct()
                    ->get();

                $allPatientIds = $doctorPatientPairs->pluck('patient_id')->unique()->values()->all();

                $oldPatientIds = PatientVisit::query()
                    ->whereIn('patient_id', $allPatientIds)
                    ->whereNotIn('shift_id', $shiftIds)
                    ->pluck('patient_id')
                    ->unique()
                    ->flip()
                    ->all();

                $counts = [];
                foreach ($doctorPatientPairs as $pair) {
                    if (! isset($oldPatientIds[$pair->patient_id])) {
                        $docId = (int) $pair->referring_doctor_id;
                        $counts[$docId] = ($counts[$docId] ?? 0) + 1;
                    }
                }

                $this->cachedNewPatients = $counts;
            }
        }

        return $this->cachedNewPatients[$doctorId] ?? 0;
    }

    protected function getTotalReferralsCombined(): int
    {
        if ($this->cachedTotalReferrals !== null) {
            return $this->cachedTotalReferrals;
        }

        $shiftIds = $this->getSelectedShiftIds();
        if (empty($shiftIds)) {
            return $this->cachedTotalReferrals = 0;
        }

        return $this->cachedTotalReferrals = PatientVisit::query()
            ->whereIn('shift_id', $shiftIds)
            ->whereNotNull('referring_doctor_id')
            ->count();
    }
}
