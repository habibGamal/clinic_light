<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DoctorReferralStatsWidget extends BaseWidget
{
    public int $doctorId;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $visitsQuery = PatientVisit::query()
            ->where('referring_doctor_id', $this->doctorId)
            ->when(! empty($this->shiftIds), fn ($q) => $q->whereIn('shift_id', $this->shiftIds));

        $totalVisits = (clone $visitsQuery)->count();
        $completedVisits = (clone $visitsQuery)->where('status', VisitStatus::Completed)->count();
        $waitingVisits = (clone $visitsQuery)->where('status', VisitStatus::Waiting)->count();
        $cancelledVisits = (clone $visitsQuery)->where('status', VisitStatus::Cancelled)->count();

        // Unique patients
        $uniquePatients = (clone $visitsQuery)->distinct('patient_id')->count('patient_id');

        // New patients (no visits outside shiftIds, or first-ever visits)
        $referralPatientIds = (clone $visitsQuery)
            ->pluck('patient_id')
            ->unique()
            ->filter()
            ->values();

        $newPatientsCount = ! empty($this->shiftIds)
            ? Patient::query()
                ->whereIn('id', $referralPatientIds)
                ->whereDoesntHave('visits', fn ($q) => $q->whereNotIn('shift_id', $this->shiftIds))
                ->count()
            : Patient::query()
                ->whereIn('id', $referralPatientIds)
                ->count();

        $visitIds = (clone $visitsQuery)->pluck('id');

        // Payments
        $paymentsQuery = Payment::query()
            ->whereIn('visit_id', $visitIds)
            ->where(function ($q): void {
                $q->where('type', '!=', 'refund')->orWhereNull('type');
            })
            ->where('amount', '>', 0);

        $totalPayments = (float) (clone $paymentsQuery)->sum('amount');

        $methodSums = (clone $paymentsQuery)
            ->selectRaw('payment_method, sum(amount) as sum_amount')
            ->groupBy('payment_method')
            ->pluck('sum_amount', 'payment_method')
            ->toArray();

        $cashPay = (float) ($methodSums[PaymentMethod::Cash->value] ?? 0);
        $cardPay = (float) ($methodSums[PaymentMethod::Card->value] ?? 0);
        $walletPay = (float) ($methodSums[PaymentMethod::Wallet->value] ?? 0);
        $bankPay = (float) ($methodSums[PaymentMethod::BankTransfer->value] ?? 0);

        $paymentsDetailsParts = [];
        if ($cashPay > 0) {
            $paymentsDetailsParts[] = 'نقدي: '.number_format($cashPay, 0).' ج.م';
        }
        if ($cardPay > 0) {
            $paymentsDetailsParts[] = 'بطاقة: '.number_format($cardPay, 0).' ج.م';
        }
        if ($walletPay > 0) {
            $paymentsDetailsParts[] = 'محفظة: '.number_format($walletPay, 0).' ج.م';
        }
        if ($bankPay > 0) {
            $paymentsDetailsParts[] = 'تحويل: '.number_format($bankPay, 0).' ج.م';
        }
        $paymentsDetailsText = ! empty($paymentsDetailsParts)
            ? implode(' | ', $paymentsDetailsParts)
            : 'لا توجد مقبوضات مسجلة';

        // Refunds
        $refundsQuery = Payment::query()
            ->whereIn('visit_id', $visitIds)
            ->where(function ($q): void {
                $q->where('type', 'refund')->orWhere('amount', '<', 0);
            });

        $totalRefunds = abs((float) (clone $refundsQuery)->sum('amount'));
        $netRevenue = $totalPayments - $totalRefunds;

        $visitsDescParts = [
            "مكتملة: {$completedVisits}",
            "انتظار: {$waitingVisits}",
        ];
        if ($cancelledVisits > 0) {
            $visitsDescParts[] = "ملغاة: {$cancelledVisits}";
        }
        $visitsDesc = implode(' | ', $visitsDescParts);

        $patientsDesc = ! empty($this->shiftIds)
            ? "منهم {$newPatientsCount} مرضى جدد بالعيادة"
            : "إجمالي {$uniquePatients} مريض محال";

        return [
            Stat::make('إجمالي الإحالات', (string) $totalVisits)
                ->description($visitsDesc)
                ->descriptionIcon(Heroicon::ClipboardDocumentList)
                ->color('primary'),

            Stat::make('المرضى المحالين', (string) $uniquePatients)
                ->description($patientsDesc)
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('info'),

            Stat::make('إجمالي المدفوعات', number_format($totalPayments, 2).' ج.م')
                ->description($paymentsDetailsText)
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('إجمالي المستردات', number_format($totalRefunds, 2).' ج.م')
                ->description($totalRefunds > 0 ? 'مستردات لزيارات ملغاة' : 'لا توجد مستردات')
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color($totalRefunds > 0 ? 'danger' : 'gray'),

            Stat::make('صافي الإيرادات', number_format($netRevenue, 2).' ج.م')
                ->description('المدفوعات بعد خصم المستردات')
                ->descriptionIcon(Heroicon::CurrencyDollar)
                ->color($netRevenue >= 0 ? 'success' : 'danger'),
        ];
    }
}
