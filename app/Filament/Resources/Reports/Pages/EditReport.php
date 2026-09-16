<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditReport extends EditRecord
{
    protected static string $resource = ReportResource::class;

    public function getSubheading(): ?string
    {
        $visit = $this->record->visitService?->visit;
        if ($visit) {
            $patientName = $visit->patient?->full_name ?? 'غير محدد';
            $serviceName = $this->record->visitService?->service?->name ?? 'غير محدد';

            return "الزيارة #{$visit->id} | المريض: {$patientName} | الخدمة: {$serviceName}";
        }

        return parent::getSubheading();
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $visit = $this->record->visitService?->visit;
        if ($visit) {
            return [
                PatientVisitResource::getUrl('index') => 'زيارات المرضى',
                PatientVisitResource::getUrl('edit', ['record' => $visit->id]) => "زيارة #{$visit->id}",
                'تعديل التقرير الطبي',
            ];
        }

        return parent::getBreadcrumbs();
    }

    protected function getRedirectUrl(): ?string
    {
        $visitId = request()->query('visit_id') ?? $this->record->visitService?->visit_id;

        if ($visitId) {
            return PatientVisitResource::getUrl('edit', ['record' => $visitId]);
        }

        return self::getResource()::getUrl('index');
    }

    protected function getCancelFormAction(): Action
    {
        $visitId = request()->query('visit_id') ?? $this->record->visitService?->visit_id;

        if ($visitId) {
            return Action::make('cancel')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
                ->url(PatientVisitResource::getUrl('edit', ['record' => $visitId]))
                ->color('gray');
        }

        return parent::getCancelFormAction();
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
