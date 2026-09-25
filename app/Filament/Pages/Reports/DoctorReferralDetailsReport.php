<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\ReferringDoctor;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class DoctorReferralDetailsReport extends Page
{
    public int|string $record;

    public ReferringDoctor $doctor;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected static ?string $slug = 'reports/doctor-referrals/{record}';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'تفاصيل إحالات الطبيب';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير';

    protected string $view = 'filament.pages.reports.doctor-referral-details';

    public function mount(int|string $record): void
    {
        $this->record = $record;
        $this->doctor = ReferringDoctor::findOrFail($record);

        $shiftIdsParam = request()->query('shift_ids');
        if (is_array($shiftIdsParam)) {
            $this->shiftIds = array_values(array_filter(array_map('intval', $shiftIdsParam)));
        } elseif (is_numeric($shiftIdsParam)) {
            $this->shiftIds = [(int) $shiftIdsParam];
        }
    }

    public function getTitle(): string|Htmlable
    {
        return "تفاصيل إحالات: {$this->doctor->name}";
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            DoctorReferralsReport::getUrl() => 'تقرير إحالات الأطباء',
            '' => $this->doctor->name,
        ];
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
                ->url(DoctorReferralsReport::getUrl()),
        ];
    }
}
