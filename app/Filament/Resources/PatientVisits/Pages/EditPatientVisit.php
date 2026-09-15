<?php

declare(strict_types=1);

namespace App\Filament\Resources\PatientVisits\Pages;

use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\PatientVisit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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
            Action::make('complete')
                ->label('إكمال الزيارة')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting)
                ->requiresConfirmation()
                ->action(function (PatientVisit $record): void {
                    $record->update(['status' => VisitStatus::Completed]);
                    Notification::make()
                        ->title('تم إكمال الزيارة بنجاح')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Action::make('cancel')
                ->label('إلغاء الزيارة')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (PatientVisit $record): bool => $record->status === VisitStatus::Waiting)
                ->requiresConfirmation()
                ->action(function (PatientVisit $record): void {
                    $record->update(['status' => VisitStatus::Cancelled]);
                    Notification::make()
                        ->title('تم إلغاء الزيارة بنجاح')
                        ->warning()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Action::make('viewInvoice')
                ->label('عرض الفاتورة')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('info')
                ->modalHeading(fn (PatientVisit $record): string => "فاتورة الزيارة #{$record->id}")
                ->modalWidth('5xl')
                ->modalContent(function (PatientVisit $record): View {
                    $invoice = app(\App\Services\InvoiceService::class)->syncInvoice($record);
                    $record->load(['patient', 'referringDoctor', 'invoice.items', 'payments']);

                    return view('filament.patient-visits.invoice-modal', [
                        'visit' => $record,
                        'invoice' => $invoice,
                        'invoiceTotal' => (float) $invoice->total_amount,
                        'totalPaid' => (float) $invoice->paid_amount,
                        'remainingDue' => (float) $invoice->remaining_amount,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق'),

            DeleteAction::make(),
        ];
    }
}
