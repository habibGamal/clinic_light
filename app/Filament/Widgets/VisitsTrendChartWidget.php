<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\VisitStatus;
use App\Models\PatientVisit;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

final class VisitsTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'حركة الزيارات اليومية (آخر 7 أيام)';

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $days = [];
        $completedData = [];
        $waitingData = [];
        $totalData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayLabel = $date->translatedFormat('D d/m');
            $days[] = $dayLabel;

            $dayQuery = PatientVisit::query()->whereDate('visit_date', $date);
            $totalCount = (clone $dayQuery)->count();
            $completedCount = (clone $dayQuery)->where('status', VisitStatus::Completed)->count();
            $waitingCount = (clone $dayQuery)->where('status', VisitStatus::Waiting)->count();

            $totalData[] = $totalCount;
            $completedData[] = $completedCount;
            $waitingData[] = $waitingCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'إجمالي الزيارات',
                    'data' => $totalData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'زيارات مكتملة',
                    'data' => $completedData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'في الانتظار',
                    'data' => $waitingData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'transparent',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
