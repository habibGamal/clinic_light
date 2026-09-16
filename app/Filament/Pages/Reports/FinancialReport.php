<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\PaymentMethod;
use App\Filament\Pages\Reports\Widgets\FinancialReportStatsWidget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Shift;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class FinancialReport extends Page
{
    public ?string $startDate = null;

    public ?string $endDate = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'التقرير المالي وحركة الخزينة';

    protected static ?string $title = 'التقرير المالي وحركة الخزينة';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير والإحصائيات';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports.financial-report';

    public function mount(): void
    {
        $this->startDate = Carbon::now()->startOfMonth()->toDateString();
        $this->endDate = Carbon::now()->toDateString();
    }

    /**
     * @return array{
     *     totalIncome: float,
     *     cashIncome: float,
     *     electronicIncome: float,
     *     totalExpenses: float,
     *     netCashFlow: float,
     *     methodsBreakdown: array<string, array{label: string, total: float, count: int}>,
     *     expensesByCategory: array<int, array{name: string, total: float, count: int}>,
     *     recentShifts: \Illuminate\Database\Eloquent\Collection<int, Shift>
     * }
     */
    public function getReportDataProperty(): array
    {
        $from = Carbon::parse($this->startDate ?? Carbon::now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($this->endDate ?? Carbon::now()->toDateString())->endOfDay();

        $paymentsQuery = Payment::query()->whereBetween('paid_at', [$from, $to]);
        $totalIncome = (float) (clone $paymentsQuery)->sum('amount');
        $cashIncome = (float) (clone $paymentsQuery)->where('payment_method', PaymentMethod::Cash)->sum('amount');
        $electronicIncome = $totalIncome - $cashIncome;

        $methodsBreakdown = [];
        foreach (PaymentMethod::cases() as $method) {
            $query = (clone $paymentsQuery)->where('payment_method', $method);
            $methodsBreakdown[$method->value] = [
                'label' => $method->getLabel() ?? $method->value,
                'total' => (float) (clone $query)->sum('amount'),
                'count' => (clone $query)->count(),
            ];
        }

        $expensesQuery = Expense::query()->whereBetween('created_at', [$from, $to]);
        $totalExpenses = (float) (clone $expensesQuery)->sum('amount');

        $expensesByCategory = [];
        $categories = ExpenseCategory::all();
        foreach ($categories as $category) {
            $catQuery = (clone $expensesQuery)->where('expense_category_id', $category->id);
            $catTotal = (float) (clone $catQuery)->sum('amount');
            if ($catTotal > 0) {
                $expensesByCategory[] = [
                    'name' => $category->name,
                    'total' => $catTotal,
                    'count' => (clone $catQuery)->count(),
                ];
            }
        }

        $recentShifts = Shift::query()
            ->with('user')
            ->whereBetween('opened_at', [$from, $to])
            ->latest('opened_at')
            ->take(10)
            ->get();

        return [
            'totalIncome' => $totalIncome,
            'cashIncome' => $cashIncome,
            'electronicIncome' => $electronicIncome,
            'totalExpenses' => $totalExpenses,
            'netCashFlow' => $totalIncome - $totalExpenses,
            'methodsBreakdown' => $methodsBreakdown,
            'expensesByCategory' => $expensesByCategory,
            'recentShifts' => $recentShifts,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            FinancialReportStatsWidget::class,
        ];
    }
}
