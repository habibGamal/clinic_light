<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\PaymentMethod;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DoctorReferralsStatsOverviewWidget extends BaseWidget
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
                Stat::make('إجمالي الإحالات', '0')
                    ->description('يرجى تحديد وردية واحدة على الأقل')
                    ->descriptionIcon(Heroicon::InformationCircle)
                    ->color('gray'),
                Stat::make('المرضى الجدد من الإحالات', '0')
                    ->description('لا توجد بيانات')
                    ->color('gray'),
                Stat::make('إجمالي مدفوعات الإحالات', '0.00 ج.م')
                    ->description('نقدي: 0 | بطاقة: 0 | محفظة: 0 | تحويل: 0')
                    ->descriptionIcon(Heroicon::Banknotes)
                    ->color('gray'),
                Stat::make('إجمالي مستردات الإحالات', '0.00 ج.م')
                    ->description('لا توجد بيانات')
                    ->descriptionIcon(Heroicon::ArrowPath)
                    ->color('gray'),
                Stat::make('صافي إيرادات الإحالات', '0.00 ج.م')
                    ->description('لا توجد بيانات')
                    ->descriptionIcon(Heroicon::CurrencyDollar)
                    ->color('gray'),
            ];
        }

        // 1. Total referrals for selected shifts (all doctors combined)
        $referralVisitsQuery = PatientVisit::query()
            ->whereIn('shift_id', $shiftIds)
            ->whereNotNull('referring_doctor_id');

        $totalReferrals = (clone $referralVisitsQuery)->count();
        $distinctDoctorsCount = (clone $referralVisitsQuery)->distinct('referring_doctor_id')->count('referring_doctor_id');

        // 2. New patients from referrals (all doctors combined)
        $referralPatientIds = (clone $referralVisitsQuery)
            ->pluck('patient_id')
            ->unique()
            ->filter()
            ->values();

        $newPatientsFromReferrals = Patient::query()
            ->whereIn('id', $referralPatientIds)
            ->whereDoesntHave('visits', fn ($q) => $q->whereNotIn('shift_id', $shiftIds))
            ->count();

        // Referral visit IDs for payments and refunds calculation
        $referralVisitIds = (clone $referralVisitsQuery)->pluck('id');

        // 3. Total payments for referrals with method breakdown
        $paymentsQuery = Payment::query()
            ->whereIn('visit_id', $referralVisitIds)
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

        // 4. Total refunds for referrals
        $refundsQuery = Payment::query()
            ->whereIn('visit_id', $referralVisitIds)
            ->where(function ($q): void {
                $q->where('type', 'refund')
                    ->orWhere('amount', '<', 0);
            });

        $totalRefunds = abs((float) (clone $refundsQuery)->sum('amount'));
        $netRevenue = $totalPayments - $totalRefunds;

        return [
            Stat::make('إجمالي الإحالات', (string) $totalReferrals)
                ->description($distinctDoctorsCount > 0 ? "عبر {$distinctDoctorsCount} أطباء إحالة" : 'لا توجد إحالات مسجلة')
                ->descriptionIcon(Heroicon::UserPlus)
                ->color('primary'),

            Stat::make('المرضى الجدد من الإحالات', (string) $newPatientsFromReferrals)
                ->description('أول تسجيل وزيارة بالعيادة عبر إحالة')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('info'),

            Stat::make('إجمالي مدفوعات الإحالات', number_format($totalPayments, 2).' ج.م')
                ->description($paymentsDetailsText)
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('إجمالي مستردات الإحالات', number_format($totalRefunds, 2).' ج.م')
                ->description($totalRefunds > 0 ? 'مبالغ مستردة لزيارات محالة ملغاة' : 'لا توجد مستردات')
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color($totalRefunds > 0 ? 'danger' : 'gray'),

            Stat::make('صافي إيرادات الإحالات', number_format($netRevenue, 2).' ج.م')
                ->description('المدفوعات بعد خصم المستردات')
                ->descriptionIcon(Heroicon::CurrencyDollar)
                ->color($netRevenue >= 0 ? 'success' : 'danger'),
        ];
    }
}
