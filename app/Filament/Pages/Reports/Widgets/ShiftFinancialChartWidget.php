<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\Expense;
use App\Models\Payment;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class ShiftFinancialChartWidget extends ChartWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'المؤشرات المالية للوردية المحددة (ج.م)';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                'datasets' => [
                    [
                        'label' => 'المبالغ (ج.م)',
                        'data' => [0, 0, 0, 0],
                        'backgroundColor' => ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
                        'borderRadius' => 6,
                    ],
                ],
                'labels' => ['المدفوعات', 'المصروفات', 'المستردات', 'صافي النقدية'],
            ];
        }

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

        $totalExpenses = (float) Expense::query()->whereIn('shift_id', $shiftIds)->sum('amount');

        $netCashFlow = $totalPayments - $totalExpenses - $totalRefunds;

        return [
            'datasets' => [
                [
                    'label' => 'المبلغ (ج.م)',
                    'data' => [$totalPayments, $totalExpenses, $totalRefunds, $netCashFlow],
                    'backgroundColor' => [
                        '#10b981', // green for payments
                        '#ef4444', // red for expenses
                        '#f59e0b', // amber for refunds
                        $netCashFlow >= 0 ? '#3b82f6' : '#64748b', // blue or slate for net
                    ],
                    'borderRadius' => 6,
                ],
            ],
            'labels' => ['المدفوعات', 'المصروفات', 'المستردات', 'صافي التدفق النقدي'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
