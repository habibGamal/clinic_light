<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Resources\Patients\PatientResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

final class DoctorReferralPatientsTableWidget extends BaseWidget
{
    public int $doctorId;

    /**
     * @var array<int>
     */
    public array $shiftIds = [];

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'قائمة المرضى المحالين من الطبيب';

    /**
     * @var array<int, array{paid: float, refunded: float, net: float}>|null
     */
    protected ?array $patientFinancials = null;

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('doctorPatients')
            ->query(
                Patient::query()
                    ->whereHas('visits', function ($q): void {
                        $q->where('referring_doctor_id', $this->doctorId)
                            ->when(! empty($this->shiftIds), fn ($sq) => $sq->whereIn('shift_id', $this->shiftIds));
                    })
                    ->withCount([
                        'visits as doctor_visits_count' => fn ($q) => $q->where('referring_doctor_id', $this->doctorId)
                            ->when(! empty($this->shiftIds), fn ($sq) => $sq->whereIn('shift_id', $this->shiftIds)),
                        'visits as total_visits_count',
                    ])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('اسم المريض')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('phone')
                    ->label('رقم الهاتف')
                    ->searchable()
                    ->copyable()
                    ->icon(Heroicon::Phone),

                TextColumn::make('gender')
                    ->label('النوع')
                    ->badge()
                    ->color(fn ($state): string => match ($state?->value ?? $state) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('doctor_visits_count')
                    ->label('الزيارات عبر الطبيب')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('total_visits_count')
                    ->label('إجمالي زيارات العيادة')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('first_visit_date')
                    ->label('أول زيارة عبر الطبيب')
                    ->state(fn (Patient $record): ?string => PatientVisit::query()
                        ->where('patient_id', $record->id)
                        ->where('referring_doctor_id', $this->doctorId)
                        ->when(! empty($this->shiftIds), fn ($q) => $q->whereIn('shift_id', $this->shiftIds))
                        ->oldest('visit_date')
                        ->value('visit_date')?->format('Y-m-d')
                    )
                    ->placeholder('-'),

                TextColumn::make('last_visit_date')
                    ->label('آخر زيارة عبر الطبيب')
                    ->state(fn (Patient $record): ?string => PatientVisit::query()
                        ->where('patient_id', $record->id)
                        ->where('referring_doctor_id', $this->doctorId)
                        ->when(! empty($this->shiftIds), fn ($q) => $q->whereIn('shift_id', $this->shiftIds))
                        ->latest('visit_date')
                        ->value('visit_date')?->format('Y-m-d')
                    )
                    ->placeholder('-'),

                TextColumn::make('total_paid')
                    ->label('المدفوع')
                    ->state(fn (Patient $record): float => $this->getPatientFinancials((int) $record->id)['paid'])
                    ->money('EGP')
                    ->color('success'),

                TextColumn::make('total_refunded')
                    ->label('المسترد')
                    ->state(fn (Patient $record): float => $this->getPatientFinancials((int) $record->id)['refunded'])
                    ->money('EGP')
                    ->color('danger'),

                TextColumn::make('net_revenue')
                    ->label('صافي الإيراد')
                    ->state(fn (Patient $record): float => $this->getPatientFinancials((int) $record->id)['net'])
                    ->money('EGP')
                    ->weight('bold')
                    ->color(fn (Patient $record): string => $this->getPatientFinancials((int) $record->id)['net'] >= 0 ? 'success' : 'danger'),
            ])
            ->defaultSort('doctor_visits_count', 'desc')
            ->recordActions([
                Action::make('viewPatient')
                    ->label('ملف المريض')
                    ->icon(Heroicon::User)
                    ->color('info')
                    ->url(fn (Patient $record): string => PatientResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('لا يوجد مرضى مسجلين لهذا الطبيب')
            ->emptyStateDescription(! empty($this->shiftIds) ? 'لم يتم العثور على مرضى محالين في الورديات المحددة.' : 'لم يتم تسجيل أي مرضى محالين عبر هذا الطبيب بعد.')
            ->emptyStateIcon(Heroicon::Users);
    }

    /**
     * @return array{paid: float, refunded: float, net: float}
     */
    protected function getPatientFinancials(int $patientId): array
    {
        if ($this->patientFinancials === null) {
            $payments = Payment::query()
                ->join('patient_visits', 'payments.visit_id', '=', 'patient_visits.id')
                ->where('patient_visits.referring_doctor_id', $this->doctorId)
                ->when(! empty($this->shiftIds), fn ($q) => $q->whereIn('patient_visits.shift_id', $this->shiftIds))
                ->selectRaw('patient_visits.patient_id, payments.type, payments.amount')
                ->get();

            $financials = [];
            foreach ($payments as $p) {
                $pid = (int) $p->patient_id;
                $financials[$pid] ??= ['paid' => 0.0, 'refunded' => 0.0, 'net' => 0.0];
                $amt = (float) $p->amount;
                if ($p->type === 'refund' || $amt < 0) {
                    $financials[$pid]['refunded'] += abs($amt);
                } else {
                    $financials[$pid]['paid'] += $amt;
                }
            }

            foreach ($financials as $pid => $data) {
                $financials[$pid]['net'] = $data['paid'] - $data['refunded'];
            }

            $this->patientFinancials = $financials;
        }

        return $this->patientFinancials[$patientId] ?? ['paid' => 0.0, 'refunded' => 0.0, 'net' => 0.0];
    }
}
