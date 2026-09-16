<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\Invoice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class OutstandingDebtsReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'تقرير الفواتير المعلقة والديون';

    protected static ?string $title = 'تقرير الفواتير والديون المعلقة';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير والإحصائيات';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.reports.outstanding-debts-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('remaining_amount', '>', 0)
                    ->with(['visit.patient', 'visit.referringDoctor'])
            )
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('visit.patient.full_name')
                    ->label('اسم المريض')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('visit.patient.phone')
                    ->label('رقم الهاتف')
                    ->searchable()
                    ->copyable()
                    ->icon(Heroicon::Phone),

                TextColumn::make('visit.visit_date')
                    ->label('تاريخ الزيارة')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('إجمالي الفاتورة')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label('المسدد')
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('remaining_amount')
                    ->label('المتبقي (المستحق)')
                    ->money('EGP')
                    ->sortable()
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
            ])
            ->defaultSort('remaining_amount', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الفاتورة')
                    ->options([
                        InvoiceStatus::Unpaid->value => InvoiceStatus::Unpaid->getLabel(),
                        InvoiceStatus::PartiallyPaid->value => InvoiceStatus::PartiallyPaid->getLabel(),
                    ]),
            ])
            ->recordActions([
                Action::make('viewVisit')
                    ->label('فتح الزيارة')
                    ->icon(Heroicon::Eye)
                    ->color('info')
                    ->url(fn (Invoice $record): string => $record->visit ? PatientVisitResource::getUrl('edit', ['record' => $record->visit]) : '#'),
            ])
            ->emptyStateHeading('لا توجد فواتير أو ديون معلقة! 🎉')
            ->emptyStateDescription('كافة الفواتير الحالية مسددة بالكامل ولا توجد أي مبالغ متأخرة على المرضى.')
            ->emptyStateIcon(Heroicon::CheckCircle);
    }
}
