<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\PatientVisit;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

final class CreateReport extends CreateRecord
{
    #[Url]
    public ?int $visit_id = null;

    #[Url]
    public ?int $visit_service_id = null;

    protected static string $resource = ReportResource::class;

    public function mount(): void
    {
        $visitId = $this->visit_id ?? request()->query('visit_id');
        $vsId = $this->visit_service_id ?? request()->query('visit_service_id');

        if (! $vsId && $visitId) {
            $vsId = \App\Models\VisitService::query()->where('visit_id', $visitId)->first()?->id;
        }

        if ($vsId) {
            $this->visit_service_id = (int) $vsId;
        }

        parent::mount();

        if ($vsId) {
            $vs = \App\Models\VisitService::query()->with(['service.serviceCategory'])->find($vsId);
            if ($vs && $vs->service) {
                $formData = $this->form->getRawState();
                $formData['visit_service_id'] = $vs->id;

                if (blank($formData['title'] ?? null)) {
                    $formData['title'] = 'تقرير طبي - '.$vs->service->name;
                }

                $reportText = $formData['report_text'] ?? null;
                $textString = is_array($reportText) ? json_encode($reportText, JSON_UNESCAPED_UNICODE) : (string) $reportText;

                if (blank($reportText) || str_contains($textString, 'الفحص الطبي المختار') || ReportResource::isDefaultOrEmptyTemplate($textString)) {
                    $formData['report_text'] = \App\Services\MedicalReportTemplateService::generateTemplate($vs->service);
                }

                $this->form->fill($formData);
            }
        }
    }

    public function getHeading(): string|Htmlable
    {
        $visitId = $this->visit_id ?? request()->query('visit_id');
        if ($visitId) {
            $visit = PatientVisit::query()->with('patient')->find($visitId);
            if ($visit) {
                return "إضافة تقرير طبي - زيارة #{$visit->id}";
            }
        }

        return parent::getHeading();
    }

    public function getSubheading(): ?string
    {
        $visitId = $this->visit_id ?? request()->query('visit_id');
        if ($visitId) {
            $visit = PatientVisit::query()->with('patient')->find($visitId);
            if ($visit) {
                $patientName = $visit->patient?->full_name ?? 'غير محدد';
                $visitDate = $visit->visit_date ? Carbon::parse($visit->visit_date)->format('Y-m-d H:i') : '';

                return "المريض: {$patientName}".($visitDate !== '' ? " | تاريخ الزيارة: {$visitDate}" : '');
            }
        }

        return parent::getSubheading();
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $visitId = $this->visit_id ?? request()->query('visit_id');
        if ($visitId) {
            $visit = PatientVisit::query()->find($visitId);
            if ($visit) {
                return [
                    PatientVisitResource::getUrl('index') => 'زيارات المرضى',
                    PatientVisitResource::getUrl('edit', ['record' => $visit->id]) => "زيارة #{$visit->id}",
                    'إضافة تقرير طبي',
                ];
            }
        }

        return parent::getBreadcrumbs();
    }

    protected function getRedirectUrl(): string
    {
        $visitId = $this->visit_id ?? request()->query('visit_id') ?? $this->record->visitService?->visit_id;

        if ($visitId) {
            return PatientVisitResource::getUrl('edit', [
                'record' => $visitId,
            ]);
        }

        return self::getResource()::getUrl('index');
    }

    protected function getCancelFormAction(): Action
    {
        $visitId = $this->visit_id ?? request()->query('visit_id');

        if ($visitId) {
            return Action::make('cancel')
                ->label(__('filament-panels::resources/pages/create-record.form.actions.cancel.label'))
                ->url(PatientVisitResource::getUrl('edit', ['record' => $visitId]))
                ->color('gray');
        }

        return parent::getCancelFormAction();
    }
}
