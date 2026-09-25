<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Services\ServicesReportService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ServiceDetailStatsWidget extends BaseWidget
{
    public int $serviceId;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(ServicesReportService::class)->getServiceDetailSummary($this->serviceId, $this->shiftIds);

        $totalVisits = $summary['total_visits'];
        $completedVisits = $summary['completed_visits'] ?? 0;
        $waitingVisits = $summary['waiting_visits'] ?? 0;
        $cancelledVisits = $summary['cancelled_visits'] ?? 0;

        $visitsDescParts = [
            "مكتملة: {$completedVisits}",
            "انتظار: {$waitingVisits}",
        ];
        if ($cancelledVisits > 0) {
            $visitsDescParts[] = "ملغاة: {$cancelledVisits}";
        }
        $visitsDesc = implode(' | ', $visitsDescParts);

        $performedCount = $summary['performed_count'];
        $uniquePatients = $summary['unique_patients'];
        $totalPayments = $summary['total_payments'];
        $totalRefunds = $summary['total_refunds'];
        $netRevenue = $summary['net_revenue'];

        return [
            Stat::make('إجمالي الزيارات', (string) $totalVisits)
                ->description($totalVisits > 0 ? $visitsDesc : 'لا توجد زيارات مسجلة')
                ->descriptionIcon(Heroicon::ClipboardDocumentList)
                ->color('primary'),

            Stat::make('مرات التنفيذ', "{$performedCount} وحدة")
                ->description('إجمالي الكميات المنفذة')
                ->descriptionIcon(Heroicon::Sparkles)
                ->color('info'),

            Stat::make('المرضى المستفيدين', (string) $uniquePatients)
                ->description("استفاد منها {$uniquePatients} مريض")
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('gray'),

            Stat::make('إجمالي المدفوعات', number_format($totalPayments, 2).' ج.م')
                ->description('إجمالي المبالغ المحصلة')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),

            Stat::make('صافي الإيراد', number_format($netRevenue, 2).' ج.م')
                ->description($totalRefunds > 0 ? 'مستردات: '.number_format($totalRefunds, 2).' ج.م' : 'المدفوعات بعد خصم المستردات')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color($netRevenue >= 0 ? 'emerald' : 'danger'),
        ];
    }
}
