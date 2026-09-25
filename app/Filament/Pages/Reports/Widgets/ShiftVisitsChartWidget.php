<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\PatientVisit;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class ShiftVisitsChartWidget extends ChartWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'توزيع حالات زيارات الوردية';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                'datasets' => [
                    [
                        'label' => 'الزيارات',
                        'data' => [0, 0, 0],
                        'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                    ],
                ],
                'labels' => ['مكتملة', 'في الانتظار', 'ملغاة'],
            ];
        }

        $visitsQuery = PatientVisit::query()->whereIn('shift_id', $shiftIds);
        $completedVisits = (clone $visitsQuery)->where('status', VisitStatus::Completed)->count();
        $waitingVisits = (clone $visitsQuery)->where('status', VisitStatus::Waiting)->count();
        $cancelledVisits = (clone $visitsQuery)->where('status', VisitStatus::Cancelled)->count();

        return [
            'datasets' => [
                [
                    'label' => 'عدد الزيارات',
                    'data' => [$completedVisits, $waitingVisits, $cancelledVisits],
                    'backgroundColor' => [
                        '#10b981', // green for completed
                        '#f59e0b', // amber for waiting
                        '#ef4444', // red for cancelled
                    ],
                ],
            ],
            'labels' => ['مكتملة', 'في الانتظار', 'ملغاة'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
