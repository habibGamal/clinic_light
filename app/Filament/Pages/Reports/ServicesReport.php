<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\ShiftStatus;
use App\Filament\Filters\ShiftFilter;
use App\Filament\Pages\Reports\Widgets\ServicePerformanceChartWidget;
use App\Filament\Pages\Reports\Widgets\ServicesTableWidget;
use App\Filament\Pages\Reports\Widgets\ServiceStatsOverviewWidget;
use App\Models\Shift;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class ServicesReport extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'reports/services';

    protected static ?string $title = 'تقرير أداء الخدمات الطبية';

    protected static ?string $navigationLabel = 'تقرير الخدمات';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function mount(): void
    {
        if (blank($this->filters)) {
            $activeShift = Shift::query()
                ->where('status', ShiftStatus::Open)
                ->when(auth()->check(), fn ($q) => $q->orderByRaw('user_id = ? desc', [auth()->id()]))
                ->latest('opened_at')
                ->first();

            if ($activeShift) {
                $this->filters = [
                    'mode' => 'shifts',
                    'shift_ids' => [$activeShift->id],
                ];
            } else {
                $this->filters = [
                    'mode' => 'period',
                    'preset' => 'today',
                ];
            }
        }

        if (method_exists($this, 'getFiltersForm')) {
            $this->getFiltersForm()->fill($this->filters);
        }
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                ShiftFilter::makeSection('تصفية الورديات')->columnSpanFull(),
            ]);
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 2;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ServiceStatsOverviewWidget::class,
            ServicePerformanceChartWidget::class,
            ServicesTableWidget::class,
        ];
    }
}
