<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

final class IncomeExpensesChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'الإيرادات مقابل المصروفات (آخر 7 أيام)';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $days = [];
        $incomeData = [];
        $expenseData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayLabel = $date->translatedFormat('D d/m');
            $days[] = $dayLabel;

            $income = (float) Payment::query()
                ->whereDate('paid_at', $date)
                ->sum('amount');

            $expense = (float) Expense::query()
                ->whereDate('created_at', $date)
                ->sum('amount');

            $incomeData[] = $income;
            $expenseData[] = $expense;
        }

        return [
            'datasets' => [
                [
                    'label' => 'الإيرادات (المقبوضات) ج.م',
                    'data' => $incomeData,
                    'backgroundColor' => '#10b981',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'المصروفات ج.م',
                    'data' => $expenseData,
                    'backgroundColor' => '#ef4444',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
