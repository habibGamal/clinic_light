<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Enums\PaymentMethod;
use App\Filament\Pages\Reports\Concerns\HasSelectedShifts;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\View\View;

final class ShiftPaymentsTableWidget extends BaseWidget
{
    use HasSelectedShifts;
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'جدول مدفوعات ومتحصلات الورديات المحددة';

    public function table(Table $table): Table
    {
        $shiftIds = $this->getSelectedShiftIds();

        $query = Payment::query()
            ->with([
                'shift.user',
                'visit.patient',
                'visit.referringDoctor',
                'visit.visitServices.service',
                'invoice',
            ])
            ->where(function ($q): void {
                $q->where('type', '!=', 'refund')
                    ->orWhereNull('type');
            })
            ->where('amount', '>', 0);

        if (empty($shiftIds)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($q) use ($shiftIds): void {
                $q->whereIn('shift_id', $shiftIds)
                    ->orWhere(fn ($sq) => $sq->whereNull('shift_id')->whereHas('visit', fn ($vq) => $vq->whereIn('shift_id', $shiftIds)));
            });
        }

        return $table
            ->queryStringIdentifier('shiftPayments')
            ->query($query)
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('visit.patient.full_name')
                    ->label('اسم المريض')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Payment $record): ?string => $record->visit?->patient?->phone)
                    ->placeholder('عميل مباشر'),

                TextColumn::make('for_what')
                    ->label('مقابل ماذا / الخدمات')
                    ->state(function (Payment $record): string {
                        $services = $record->visit?->visitServices
                            ?->map(fn ($vs) => $vs->service?->name)
                            ?->filter()
                            ?->values()
                            ?->all() ?? [];

                        if (! empty($services)) {
                            return implode('، ', $services);
                        }

                        if (! empty($record->invoice?->invoice_number)) {
                            return "فاتورة #{$record->invoice->invoice_number}";
                        }

                        if (! empty($record->notes)) {
                            return $record->notes;
                        }

                        return 'سداد كشف وزيارة';
                    })
                    ->description(function (Payment $record): ?string {
                        $parts = [];
                        if ($record->invoice?->invoice_number) {
                            $parts[] = "فاتورة #{$record->invoice->invoice_number}";
                        }
                        if (! empty($record->notes) && $record->visit?->visitServices?->isNotEmpty()) {
                            $parts[] = $record->notes;
                        }

                        return ! empty($parts) ? implode(' | ', $parts) : null;
                    })
                    ->wrap(),

                TextColumn::make('amount')
                    ->label('المبلغ المدفوع')
                    ->money('EGP')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->badge()
                    ->color(fn (PaymentMethod $state): string => match ($state) {
                        PaymentMethod::Cash => 'success',
                        PaymentMethod::Card => 'info',
                        PaymentMethod::Wallet => 'warning',
                        PaymentMethod::BankTransfer => 'purple',
                    })
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('تاريخ وتوقيت السداد')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),

                TextColumn::make('shift_id')
                    ->label('الوردية')
                    ->formatStateUsing(fn ($state): string => "وردية #{$state}")
                    ->badge()
                    ->color('gray'),

                TextColumn::make('shift.user.name')
                    ->label('الكاشير / المسؤول')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentMethod::class),
            ])
            ->recordActions([
                Action::make('viewVisitDetails')
                    ->label('عرض الزيارة')
                    ->icon(Heroicon::Eye)
                    ->color('info')
                    ->modalHeading(fn (Payment $record): string => "تفاصيل الزيارة #{$record->visit?->id} - {$record->visit?->patient?->full_name}")
                    ->modalWidth('5xl')
                    ->modalContent(fn (Payment $record): View => view('filament.pages.reports.visit-details-modal', [
                        'visit' => $record->visit->loadMissing(['patient', 'referringDoctor', 'invoice.items', 'payments', 'visitServices.service', 'shift.user']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->visible(fn (Payment $record): bool => $record->visit !== null),
            ])
            ->emptyStateHeading('لا توجد مدفوعات مسجلة')
            ->emptyStateDescription(empty($shiftIds) ? 'يرجى تحديد وردية واحدة على الأقل لعرض مدفوعاتها.' : 'لم يتم تسجيل أي مدفوعات في الورديات المحددة.')
            ->emptyStateIcon(Heroicon::Banknotes);
    }
}
