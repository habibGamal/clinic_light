<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class FinancialReportStatsWidget extends BaseWidget
{
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();

        // Total payments overall & this month
        $monthPayments = Payment::query()->where('paid_at', '>=', $startOfMonth);
        $totalMonthIncome = (float) (clone $monthPayments)->sum('amount');
        $cashMonthIncome = (float) (clone $monthPayments)->where('payment_method', PaymentMethod::Cash)->sum('amount');
        $electronicMonthIncome = $totalMonthIncome - $cashMonthIncome;

        // Total expenses this month
        $monthExpenses = Expense::query()->where('created_at', '>=', $startOfMonth);
        $totalMonthExpenses = (float) (clone $monthExpenses)->sum('amount');

        // Net cash flow
        $netCashFlow = $totalMonthIncome - $totalMonthExpenses;

        // All-time income for reference
        $allTimeIncome = (float) Payment::query()->sum('amount');

        return [
            Stat::make('مقبوضات الشهر الحالي', number_format($totalMonthIncome, 2).' ج.م')
                ->description('إجمالي المقبوضات منذ أول الشهر')
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('success'),

            Stat::make('المقبوضات النقدية (كاش)', number_format($cashMonthIncome, 2).' ج.م')
                ->description('نسبة الكاش: '.($totalMonthIncome > 0 ? round(($cashMonthIncome / $totalMonthIncome) * 100, 1) : 0).'%')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('primary'),

            Stat::make('المقبوضات الإلكترونية والبطاقات', number_format($electronicMonthIncome, 2).' ج.م')
                ->description('بطاقات ومحافظ وتحويلات بنكية')
                ->descriptionIcon(Heroicon::CreditCard)
                ->color('info'),

            Stat::make('مصروفات الشهر الحالي', number_format($totalMonthExpenses, 2).' ج.م')
                ->description('إجمالي بنود الصرف المسجلة')
                ->descriptionIcon(Heroicon::ArrowTrendingDown)
                ->color('danger'),

            Stat::make('صافي السيولة / الأرباح للشهر', number_format($netCashFlow, 2).' ج.م')
                ->description($netCashFlow >= 0 ? 'صافي تدفق نقدي موجب' : 'عجز في حركة النقدية')
                ->descriptionIcon($netCashFlow >= 0 ? Heroicon::Sparkles : Heroicon::ExclamationCircle)
                ->color($netCashFlow >= 0 ? 'success' : 'danger'),

            Stat::make('إجمالي الإيرادات التراكمية', number_format($allTimeIncome, 2).' ج.م')
                ->description('إجمالي حركة المقبوضات الشاملة')
                ->descriptionIcon(Heroicon::CircleStack)
                ->color('gray'),
        ];
    }
}
