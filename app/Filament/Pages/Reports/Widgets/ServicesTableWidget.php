<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Filament\Pages\Reports\ServiceDetailsReport;
use App\Models\Service;
use App\Services\ServicesReportService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

final class ServicesTableWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'جدول إحصائيات أداء الخدمات للورديات المحددة';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $cachedServices = null;

    public function table(Table $table): Table
    {
        $shiftIds = $this->getSelectedShiftIds();

        return $table
            ->queryStringIdentifier('servicesPerformance')
            ->query(
                Service::query()
                    ->with('serviceCategory')
                    ->when(
                        ! empty($shiftIds),
                        fn ($q) => $q->whereHas('visitServices.visit', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)),
                        fn ($q) => $q->whereRaw('1 = 0')
                    )
                    ->withCount([
                        'visitServices as performed_count' => fn ($q) => $q->whereHas('visit', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)),
                    ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الخدمة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Service $record): ?string => $record->code ? "كود: {$record->code}" : null),

                TextColumn::make('serviceCategory.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('info')
                    ->placeholder('عام'),

                TextColumn::make('performed_count')
                    ->label('مرات التنفيذ')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->state(fn (Service $record): int => (int) $this->getServiceMetric($record->id, 'performed_count')),

                TextColumn::make('completed_visits_count')
                    ->label('الزيارات المكتملة')
                    ->badge()
                    ->color('success')
                    ->state(fn (Service $record): int => (int) $this->getServiceMetric($record->id, 'completed_visits_count')),

                TextColumn::make('waiting_visits_count')
                    ->label('زيارات في الانتظار')
                    ->badge()
                    ->color('warning')
                    ->state(fn (Service $record): int => (int) $this->getServiceMetric($record->id, 'waiting_visits_count')),

                TextColumn::make('cancelled_visits_count')
                    ->label('الزيارات الملغاة')
                    ->badge()
                    ->color('danger')
                    ->state(fn (Service $record): int => (int) $this->getServiceMetric($record->id, 'cancelled_visits_count')),

                TextColumn::make('total_payments')
                    ->label('إجمالي المدفوعات')
                    ->money('EGP')
                    ->color('success')
                    ->state(fn (Service $record): float => (float) $this->getServiceMetric($record->id, 'total_payments')),

                TextColumn::make('total_refunds')
                    ->label('إجمالي المستردات')
                    ->money('EGP')
                    ->color('danger')
                    ->state(fn (Service $record): float => (float) $this->getServiceMetric($record->id, 'total_refunds')),

                TextColumn::make('net_revenue')
                    ->label('صافي الإيراد')
                    ->money('EGP')
                    ->color(fn (Service $record): string => ((float) $this->getServiceMetric($record->id, 'net_revenue')) >= 0 ? 'emerald' : 'danger')
                    ->state(fn (Service $record): float => (float) $this->getServiceMetric($record->id, 'net_revenue')),

                TextColumn::make('percent_of_services')
                    ->label('نسبة الخدمات')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Service $record): string => $this->getServiceMetric($record->id, 'percent_of_services').'%'),
            ])
            ->defaultSort('performed_count', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('تصنيف الخدمة')
                    ->relationship('serviceCategory', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('عرض التفاصيل')
                    ->icon(Heroicon::Eye)
                    ->color('primary')
                    ->url(fn (Service $record): string => ServiceDetailsReport::getUrl([
                        'record' => $record->id,
                        'shift_ids' => $shiftIds,
                    ])),
            ])
            ->emptyStateHeading('لا توجد خدمات مسجلة')
            ->emptyStateDescription(
                empty($shiftIds)
                ? 'يرجى تحديد وردية واحدة على الأقل لعرض بيانات الخدمات.'
                : 'لم يتم تسجيل أي خدمات منفذة في هذه الورديات.'
            )
            ->emptyStateIcon(Heroicon::Sparkles);
    }

    /**
     * Retrieve precomputed metric value for a specific service.
     */
    protected function getServiceMetric(int $serviceId, string $metric): mixed
    {
        if ($this->cachedServices === null) {
            $shiftIds = $this->getSelectedShiftIds();
            if (empty($shiftIds)) {
                $this->cachedServices = [];
            } else {
                $data = app(ServicesReportService::class)->getReportData($shiftIds);
                $this->cachedServices = $data['services'];
            }
        }

        return $this->cachedServices[$serviceId][$metric] ?? 0;
    }
}
