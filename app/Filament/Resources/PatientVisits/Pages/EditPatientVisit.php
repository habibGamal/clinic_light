<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\Pages;

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\PatientVisit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

final class EditPatientVisit extends EditRecord
{
    protected static string $resource = PatientVisitResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewInvoice')
                ->label('عرض الفاتورة')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->modalHeading(fn (PatientVisit $record): string => "فاتورة الزيارة #{$record->id}")
                ->modalWidth('5xl')
                ->modalContent(function (PatientVisit $record): View {
                    $record->load(['patient', 'referringDoctor', 'visitServices.service', 'visitServices.selectedOptions.serviceOption', 'payments']);
                    $invoiceTotal = (float) $record->visitServices->sum('total');
                    $totalPaid = (float) $record->payments->sum('amount');
                    $remainingDue = max(0, $invoiceTotal - $totalPaid);

                    return view('filament.patient-visits.invoice-modal', [
                        'visit' => $record,
                        'invoiceTotal' => $invoiceTotal,
                        'totalPaid' => $totalPaid,
                        'remainingDue' => $remainingDue,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق'),

            DeleteAction::make(),
        ];
    }
}
