<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\PatientVisit;
use App\Models\ReferringDoctor;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class DoctorReferralsReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?string $navigationLabel = 'تقرير الأطباء المحيلين والعمولات';

    protected static ?string $title = 'تقرير إحالات الأطباء والعمولات';

    protected static string|UnitEnum|null $navigationGroup = 'التقارير والإحصائيات';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.reports.doctor-referrals-report';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ReferringDoctor::query()
                    ->withCount('patientVisits')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الطبيب')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('specialization')
                    ->label('التخصص')
                    ->searchable()
                    ->placeholder('غير محدد'),

                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('patient_visits_count')
                    ->label('عدد الزيارات المحولة')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('invoiced_total')
                    ->label('إجمالي قيمة الفحوصات (ج.م)')
                    ->state(function (ReferringDoctor $record): string {
                        $visitIds = PatientVisit::query()->where('referring_doctor_id', $record->id)->pluck('id');
                        $total = (float) \App\Models\Invoice::query()->whereIn('visit_id', $visitIds)->sum('total_amount');

                        return number_format($total, 2);
                    }),

                TextColumn::make('paid_total')
                    ->label('المبالغ المحصلة (ج.م)')
                    ->state(function (ReferringDoctor $record): string {
                        $visitIds = PatientVisit::query()->where('referring_doctor_id', $record->id)->pluck('id');
                        $paid = (float) \App\Models\Payment::query()->whereIn('visit_id', $visitIds)->sum('amount');

                        return number_format($paid, 2);
                    })
                    ->color('success'),
            ])
            ->defaultSort('patient_visits_count', 'desc')
            ->emptyStateHeading('لا يوجد أطباء محيلين مسجلين')
            ->emptyStateDescription('عند إضافة أطباء محيلين وربطهم بزيارات المرضى، سيتم تجميع كافة الإحصائيات هنا تلقائياً.')
            ->emptyStateIcon(Heroicon::UserPlus);
    }
}
