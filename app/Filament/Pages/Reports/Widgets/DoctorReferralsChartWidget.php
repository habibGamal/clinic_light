<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\ReferringDoctor;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

final class DoctorReferralsChartWidget extends ChartWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'إجمالي الإحالات لكل طبيب';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                'datasets' => [
                    [
                        'label' => 'عدد الإحالات',
                        'data' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $doctors = ReferringDoctor::query()
            ->whereHas('patientVisits', fn ($q) => $q->whereIn('shift_id', $shiftIds))
            ->withCount(['patientVisits as referrals_count' => fn ($q) => $q->whereIn('shift_id', $shiftIds)])
            ->orderByDesc('referrals_count')
            ->take(20)
            ->get();

        $labels = $doctors->pluck('name')->toArray();
        $counts = $doctors->pluck('referrals_count')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'عدد الإحالات',
                    'data' => $counts,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#2563eb',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
