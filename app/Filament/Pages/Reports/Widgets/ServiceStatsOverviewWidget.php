<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Services\ServicesReportService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ServiceStatsOverviewWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $shiftIds = $this->getSelectedShiftIds();

        if (empty($shiftIds)) {
            return [
                Stat::make('الخدمة الأكثر طلباً', 'لا توجد بيانات')
                    ->description('يرجى تحديد وردية واحدة على الأقل')
                    ->descriptionIcon(Heroicon::InformationCircle)
                    ->color('gray'),

                Stat::make('الخدمة الأكثر دخلاً', '0.00 ج.م')
                    ->description('لا توجد بيانات')
                    ->descriptionIcon(Heroicon::Banknotes)
                    ->color('gray'),

                Stat::make('إجمالي الخدمات المنفذة', '0')
                    ->description('لا توجد بيانات')
                    ->descriptionIcon(Heroicon::ClipboardDocumentCheck)
                    ->color('gray'),

                Stat::make('صافي إيرادات الخدمات', '0.00 ج.م')
                    ->description('لا توجد بيانات')
                    ->descriptionIcon(Heroicon::CheckCircle)
                    ->color('gray'),
            ];
        }

        $reportService = app(ServicesReportService::class);
        $data = $reportService->getReportData($shiftIds);
        $overall = $data['overall'];

        // 1. Top Performed Service Card
        $topPerformed = $overall['top_performed_service'];
        if ($topPerformed) {
            $topPerformedStat = Stat::make('الخدمة الأكثر طلباً', $topPerformed['name'])
                ->description("تم تنفيذها {$topPerformed['count']} مرة ({$topPerformed['percent']}% من إجمالي الخدمات)")
                ->descriptionIcon(Heroicon::ArrowTrendingUp)
                ->color('primary');
        } else {
            $topPerformedStat = Stat::make('الخدمة الأكثر طلباً', 'لا توجد بيانات')
                ->description('لم يتم تسجيل خدمات منفذة في هذه الورديات')
                ->descriptionIcon(Heroicon::InformationCircle)
                ->color('gray');
        }

        // 2. Top Revenue Service Card
        $topRevenue = $overall['top_revenue_service'];
        if ($topRevenue) {
            $topRevenueStat = Stat::make('الخدمة الأكثر دخلاً', number_format($topRevenue['revenue'], 2).' ج.م')
                ->description("{$topRevenue['name']} ({$topRevenue['percent']}% من صافي الإيرادات)")
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success');
        } else {
            $topRevenueStat = Stat::make('الخدمة الأكثر دخلاً', '0.00 ج.م')
                ->description('لا توجد إيرادات مسجلة')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('gray');
        }

        // 3. Total Services Performed Volume Card
        $totalPerformed = $overall['total_performed_count'];
        $completedVisits = $overall['completed_visits_count'];
        $waitingVisits = $overall['waiting_visits_count'];
        $cancelledVisits = $overall['cancelled_visits_count'];

        $totalPerformedStat = Stat::make('إجمالي الخدمات المنفذة', "{$totalPerformed} خدمة")
            ->description("مكتملة: {$completedVisits} | في الانتظار: {$waitingVisits} | ملغاة: {$cancelledVisits}")
            ->descriptionIcon(Heroicon::ClipboardDocumentCheck)
            ->color('info');

        // 4. Net Service Revenue Card
        $netRevenue = $overall['net_revenue'];
        $payments = $overall['total_payments'];
        $refunds = $overall['total_refunds'];

        $financialDesc = 'المدفوعات: '.number_format($payments, 0).' ج.م | المستردات: '.number_format($refunds, 0).' ج.م';

        $netRevenueStat = Stat::make('صافي إيرادات الخدمات', number_format($netRevenue, 2).' ج.م')
            ->description($financialDesc)
            ->descriptionIcon(Heroicon::CheckCircle)
            ->color($netRevenue >= 0 ? 'emerald' : 'danger');

        return [
            $topPerformedStat,
            $topRevenueStat,
            $totalPerformedStat,
            $netRevenueStat,
        ];
    }
}
