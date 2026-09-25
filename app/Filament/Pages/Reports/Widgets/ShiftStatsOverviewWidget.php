<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\PaymentMethod;
use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\Expense;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ShiftStatsOverviewWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                Stat::make('إجمالي الزيارات', '0')
                    ->description('يرجى تحديد وردية واحدة على الأقل')
                    ->descriptionIcon(Heroicon::InformationCircle)
                    ->color('gray'),
                Stat::make('الزيارات المكتملة', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
                Stat::make('زيارات في الانتظار', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
                Stat::make('الزيارات الملغاة', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
                Stat::make('إجمالي المدفوعات', '0.00 ج.م')
                    ->description('نقدي: 0 | بطاقة: 0 | محفظة: 0 | تحويل: 0')
                    ->descriptionIcon(Heroicon::Banknotes)
                    ->color('gray'),
                Stat::make('إجمالي المستردات', '0.00 ج.م')
                    ->description('نقدي: 0 | بطاقة: 0 | محفظة: 0 | تحويل: 0')
                    ->descriptionIcon(Heroicon::ArrowPath)
                    ->color('gray'),
                Stat::make('إجمالي المصروفات', '0.00 ج.م')
                    ->description('0 سند صرف')
                    ->descriptionIcon(Heroicon::ArrowTrendingDown)
                    ->color('gray'),
                Stat::make('صافي الإيراد', '0.00 ج.م')
                    ->description('المدفوعات - المستردات - المصروفات')
                    ->descriptionIcon(Heroicon::Scale)
                    ->color('gray'),
                Stat::make('المرضى الجدد', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
                Stat::make('الإحالات الجديدة', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
            ];
        }

        // 1-4. Visits counts
        $visitsQuery = PatientVisit::query()->whereIn('shift_id', $shiftIds);
        $totalVisits = (clone $visitsQuery)->count();
        $completedVisits = (clone $visitsQuery)->where('status', VisitStatus::Completed)->count();
        $waitingVisits = (clone $visitsQuery)->where('status', VisitStatus::Waiting)->count();
        $cancelledVisits = (clone $visitsQuery)->where('status', VisitStatus::Cancelled)->count();

        // 5. Total payments with method breakdown
        $paymentsQuery = Payment::query()
            ->where(function ($q) use ($shiftIds): void {
                $q->whereIn('shift_id', $shiftIds)
                    ->orWhere(fn ($sq) => $sq->whereNull('shift_id')->whereHas('visit', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)));
            })
            ->where(function ($q): void {
                $q->where('type', '!=', 'refund')
                    ->orWhereNull('type');
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

        $paymentsDetailsParts = [
            'نقدي: '.number_format($cashPay, 0).' ج.م',
            'بطاقة: '.number_format($cardPay, 0).' ج.م',
            'محفظة: '.number_format($walletPay, 0).' ج.م',
        ];
        if ($bankPay > 0) {
            $paymentsDetailsParts[] = 'تحويل: '.number_format($bankPay, 0).' ج.م';
        }
        $paymentsDetailsText = implode(' | ', $paymentsDetailsParts);

        // 6. Total refunds with method breakdown
        $refundsQuery = Payment::query()
            ->where(function ($q) use ($shiftIds): void {
                $q->whereIn('shift_id', $shiftIds)
                    ->orWhere(fn ($sq) => $sq->whereNull('shift_id')->whereHas('visit', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)));
            })
            ->where(function ($q): void {
                $q->where('type', 'refund')
                    ->orWhere('amount', '<', 0);
            });

        $totalRefunds = abs((float) (clone $refundsQuery)->sum('amount'));

        $refundMethodSums = (clone $refundsQuery)
            ->selectRaw('payment_method, sum(abs(amount)) as sum_amount')
            ->groupBy('payment_method')
            ->pluck('sum_amount', 'payment_method')
            ->toArray();

        $cashRefund = (float) ($refundMethodSums[PaymentMethod::Cash->value] ?? 0);
        $cardRefund = (float) ($refundMethodSums[PaymentMethod::Card->value] ?? 0);
        $walletRefund = (float) ($refundMethodSums[PaymentMethod::Wallet->value] ?? 0);
        $bankRefund = (float) ($refundMethodSums[PaymentMethod::BankTransfer->value] ?? 0);

        $refundsDetailsParts = [
            'نقدي: '.number_format($cashRefund, 0).' ج.م',
            'بطاقة: '.number_format($cardRefund, 0).' ج.م',
            'محفظة: '.number_format($walletRefund, 0).' ج.م',
        ];
        if ($bankRefund > 0) {
            $refundsDetailsParts[] = 'تحويل: '.number_format($bankRefund, 0).' ج.م';
        }
        $refundsDetailsText = implode(' | ', $refundsDetailsParts);

        // 7. Total expenses
        $expensesQuery = Expense::query()->whereIn('shift_id', $shiftIds);
        $totalExpenses = (float) (clone $expensesQuery)->sum('amount');
        $expensesCount = (clone $expensesQuery)->count();

        // 8. Net revenue = total payments - total refunds - total expenses
        $netRevenue = $totalPayments - $totalRefunds - $totalExpenses;

        // 8. New patients for the selected shifts
        $patientIds = PatientVisit::query()
            ->whereIn('shift_id', $shiftIds)
            ->pluck('patient_id')
            ->unique()
            ->filter()
            ->values();

        $newPatientsCount = Patient::query()
            ->whereIn('id', $patientIds)
            ->whereDoesntHave('visits', fn ($q) => $q->whereNotIn('shift_id', $shiftIds))
            ->count();

        // 9. New referrals for the selected shifts
        $referralVisits = PatientVisit::query()
            ->whereIn('shift_id', $shiftIds)
            ->whereNotNull('referring_doctor_id');

        $newReferralsCount = (clone $referralVisits)->count();
        $distinctDoctorsCount = (clone $referralVisits)->distinct('referring_doctor_id')->count('referring_doctor_id');

        $completedPercentage = $totalVisits > 0 ? round(($completedVisits / $totalVisits) * 100, 1) : 0;

        return [
            Stat::make('إجمالي الزيارات', (string) $totalVisits)
                ->description('كافة الزيارات المسجلة في الوردية')
                ->descriptionIcon(Heroicon::ClipboardDocumentList)
                ->color('primary'),

            Stat::make('الزيارات المكتملة', (string) $completedVisits)
                ->description("{$completedPercentage}% من إجمالي الزيارات")
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('زيارات في الانتظار', (string) $waitingVisits)
                ->description('في قائمة الانتظار الحالية')
                ->descriptionIcon(Heroicon::Clock)
                ->color($waitingVisits > 0 ? 'warning' : 'gray'),

            Stat::make('الزيارات الملغاة', (string) $cancelledVisits)
                ->description('زيارات ملغاة ومستردة')
                ->descriptionIcon(Heroicon::XCircle)
                ->color($cancelledVisits > 0 ? 'danger' : 'gray'),

            Stat::make('إجمالي المدفوعات', number_format($totalPayments, 2).' ج.م')
                ->description($paymentsDetailsText)
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('إجمالي المستردات', number_format($totalRefunds, 2).' ج.م')
                ->description($refundsDetailsText)
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color($totalRefunds > 0 ? 'danger' : 'gray'),

            Stat::make('إجمالي المصروفات', number_format($totalExpenses, 2).' ج.م')
                ->description("{$expensesCount} سند صرف مسجل")
                ->descriptionIcon(Heroicon::ArrowTrendingDown)
                ->color($totalExpenses > 0 ? 'danger' : 'gray'),

            Stat::make('صافي الإيراد', number_format($netRevenue, 2).' ج.م')
                ->description('المدفوعات - المستردات - المصروفات')
                ->descriptionIcon($netRevenue >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($netRevenue > 0 ? 'success' : ($netRevenue < 0 ? 'danger' : 'gray')),

            Stat::make('المرضى الجدد', (string) $newPatientsCount)
                ->description('أول تسجيل وزيارة بالعيادة')
                ->descriptionIcon(Heroicon::UserPlus)
                ->color('info'),

            Stat::make('الإحالات الجديدة', (string) $newReferralsCount)
                ->description("عبر {$distinctDoctorsCount} أطباء إحالة")
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('purple'),
        ];
    }
}
