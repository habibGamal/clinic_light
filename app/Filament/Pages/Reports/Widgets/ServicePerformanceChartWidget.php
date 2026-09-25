<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Services\ServicesReportService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class ServicePerformanceChartWidget extends ChartWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'مقارنة أداء الخدمات الأكثر طلباً (عدد مرات التنفيذ)';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                'datasets' => [
                    [
                        'label' => 'مرات التنفيذ',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $reportService = app(ServicesReportService::class);
        $data = $reportService->getReportData($shiftIds);
        $chartData = $data['top_for_chart'];

        return [
            'datasets' => [
                [
                    'label' => 'مرات التنفيذ',
                    'data' => $chartData['counts'],
                    'backgroundColor' => [
                        '#3b82f6',
                        '#10b981',
                        '#6366f1',
                        '#f59e0b',
                        '#ec4899',
                        '#8b5cf6',
                        '#14b8a6',
                        '#f97316',
                        '#64748b',
                    ],
                    'borderColor' => '#1e293b',
                    'borderWidth' => 0.5,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $chartData['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
