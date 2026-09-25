<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Service;
use App\Services\ServicesReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class ServiceDetailsReport extends Page
{
    public int|string $record;

    public Service $service;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected static ?string $slug = 'reports/services/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'تفاصيل أداء الخدمة';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    protected string $view = 'filament.pages.reports.service-details';

    public function mount(int|string $record): void
    {
        $this->record = $record;
        $this->service = Service::with('serviceCategory')->findOrFail($record);

        $shiftIdsParam = request()->query('shift_ids');
        if (is_array($shiftIdsParam)) {
            $this->shiftIds = array_values(array_filter(array_map('intval', $shiftIdsParam)));
        } elseif (is_numeric($shiftIdsParam)) {
            $this->shiftIds = [(int) $shiftIdsParam];
        }
    }

    public function getTitle(): string|Htmlable
    {
        return "تفاصيل أداء خدمة: {$this->service->name}";
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ServicesReport::getUrl() => 'تقرير الخدمات',
            '' => $this->service->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getServiceSummary(): array
    {
        return app(ServicesReportService::class)->getServiceDetailSummary((int) $this->service->id, $this->shiftIds);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('العودة للتقرير')
                ->icon(Heroicon::ArrowRight)
                ->color('gray')
                ->url(ServicesReport::getUrl()),
        ];
    }
}
