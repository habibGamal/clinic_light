<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Shift;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ClinicStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $today = Carbon::today();

        // 1. Today's Visits Stats
        $todayVisitsQuery = PatientVisit::query()->whereDate('visit_date', $today);
        $totalTodayVisits = (clone $todayVisitsQuery)->count();
        $completedVisits = (clone $todayVisitsQuery)->where('status', VisitStatus::Completed)->count();
        $waitingVisits = (clone $todayVisitsQuery)->where('status', VisitStatus::Waiting)->count();
        $cancelledVisits = (clone $todayVisitsQuery)->where('status', VisitStatus::Cancelled)->count();

        // 7-day visits trend for sparkline
        $visitsChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $visitsChart[] = (float) PatientVisit::query()->whereDate('visit_date', $day)->count();
        }

        // 2. Today's Collections & Incomes
        $todayPaymentsQuery = Payment::query()->whereDate('paid_at', $today);
        $todayIncome = (float) (clone $todayPaymentsQuery)->sum('amount');
        $cashIncome = (float) (clone $todayPaymentsQuery)->where('payment_method', \App\Enums\PaymentMethod::Cash)->sum('amount');
        $electronicIncome = $todayIncome - $cashIncome;

        $incomeChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $incomeChart[] = (float) Payment::query()->whereDate('paid_at', $day)->sum('amount');
        }

        // 3. Today's Expenses
        $todayExpensesQuery = Expense::query()->whereDate('created_at', $today);
        $todayExpenses = (float) (clone $todayExpensesQuery)->sum('amount');
        $expensesCount = (clone $todayExpensesQuery)->count();

        // 4. Net Cash Flow
        $netCashFlow = $todayIncome - $todayExpenses;

        // 5. Total Outstanding Debt / Unpaid Invoices
        $outstandingQuery = Invoice::query()->where('remaining_amount', '>', 0);
        $totalOutstanding = (float) (clone $outstandingQuery)->sum('remaining_amount');
        $unpaidCount = (clone $outstandingQuery)->count();

        // 6. Active Shift Status
        $activeShift = Shift::query()
            ->with('user')
            ->where('status', ShiftStatus::Open)
            ->latest('opened_at')
            ->first();

        $shiftDescription = $activeShift
            ? "الكاشير: {$activeShift->user?->name} | الافتتاحي: ".number_format((float) $activeShift->opening_balance, 2).' ج.م'
            : 'لا توجد وردية مفتوحة حالياً';

        return [
            Stat::make('زيارات اليوم', (string) $totalTodayVisits)
                ->description("مكتملة: {$completedVisits} | انتظار: {$waitingVisits} | ملغاة: {$cancelledVisits}")
                ->descriptionIcon(Heroicon::Clock)
                ->color($waitingVisits > 0 ? 'warning' : 'primary')
                ->chart($visitsChart),

            Stat::make('مقبوضات اليوم', number_format($todayIncome, 2).' ج.م')
                ->description('نقداً: '.number_format($cashIncome, 2).' | إلكتروني: '.number_format($electronicIncome, 2))
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('success')
                ->chart($incomeChart),

            Stat::make('مصروفات اليوم', number_format($todayExpenses, 2).' ج.م')
                ->description("عدد سندات الصرف: {$expensesCount}")
                ->descriptionIcon(Heroicon::ArrowTrendingDown)
                ->color('danger'),

            Stat::make('صافي حركة الصندوق اليوم', number_format($netCashFlow, 2).' ج.م')
                ->description($netCashFlow >= 0 ? 'فائض نقدي إيجابي' : 'عجز في السيولة اليومية')
                ->descriptionIcon($netCashFlow >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($netCashFlow >= 0 ? 'success' : 'danger'),

            Stat::make('إجمالي الديون المعلقة', number_format($totalOutstanding, 2).' ج.م')
                ->description("لدى {$unpaidCount} فاتورة غير مسددة بالكامل")
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color($totalOutstanding > 0 ? 'warning' : 'success'),

            Stat::make('حالة الوردية الحالية', $activeShift ? 'وردية مفتوحة نشطة' : 'الوردية مغلقة')
                ->description($shiftDescription)
                ->descriptionIcon($activeShift ? Heroicon::LockOpen : Heroicon::LockClosed)
                ->color($activeShift ? 'success' : 'gray'),
        ];
    }
}
