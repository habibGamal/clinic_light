<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\DoctorReferralDetailsReport;
use App\Filament\Pages\Reports\DoctorReferralsReport;
use App\Filament\Pages\Reports\Widgets\DoctorReferralPatientsTableWidget;
use App\Filament\Pages\Reports\Widgets\DoctorReferralsChartWidget;
use App\Filament\Pages\Reports\Widgets\DoctorReferralsStatsOverviewWidget;
use App\Filament\Pages\Reports\Widgets\DoctorReferralsTableWidget;
use App\Filament\Pages\Reports\Widgets\DoctorReferralVisitsTableWidget;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\ReferringDoctor;
use App\Models\Shift;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can access the doctor referrals report page via url', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    get(DoctorReferralsReport::getUrl())
        ->assertOk();
});

it('auto selects currently opened shift if exists', function (): void {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHours(8),
    ]);

    $openShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    livewire(DoctorReferralsReport::class)
        ->assertOk()
        ->assertSet('filters.mode', 'shifts')
        ->assertSet('filters.shift_ids', [$openShift->id]);
});

it('defaults to period mode today if no open shift exists', function (): void {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDay(),
        'closed_at' => now()->subDay()->addHours(8),
    ]);

    livewire(DoctorReferralsReport::class)
        ->assertOk()
        ->assertSet('filters.mode', 'period')
        ->assertSet('filters.preset', 'today');
});

it('renders the 4 stats cards with accurate statistics and breakdown', function (): void {
    $openShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctor1 = ReferringDoctor::factory()->create(['name' => 'د. أحمد علي']);
    $doctor2 = ReferringDoctor::factory()->create(['name' => 'د. محمود حسن']);

    // 1 new patient for doctor 1 (completed visit)
    $newPatient1 = Patient::factory()->create(['full_name' => 'مريض جديد 1']);
    $visit1 = PatientVisit::factory()->create([
        'patient_id' => $newPatient1->id,
        'shift_id' => $openShift->id,
        'referring_doctor_id' => $doctor1->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    // 1 returning patient (has previous visit in closed shift) with doctor 2 (waiting visit)
    $closedShift = Shift::factory()->create([
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subDays(5),
    ]);
    $returningPatient = Patient::factory()->create(['full_name' => 'مريض قديم']);
    PatientVisit::factory()->create([
        'patient_id' => $returningPatient->id,
        'shift_id' => $closedShift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now()->subDays(5),
    ]);

    $visit2 = PatientVisit::factory()->create([
        'patient_id' => $returningPatient->id,
        'shift_id' => $openShift->id,
        'referring_doctor_id' => $doctor2->id,
        'status' => VisitStatus::Waiting,
        'visit_date' => now(),
    ]);

    // 1 cancelled visit for doctor 1
    $visit3 = PatientVisit::factory()->create([
        'patient_id' => $newPatient1->id,
        'shift_id' => $openShift->id,
        'referring_doctor_id' => $doctor1->id,
        'status' => VisitStatus::Cancelled,
        'visit_date' => now(),
    ]);

    // 1 visit with NO referral (should be ignored by doctor referrals report)
    $directPatient = Patient::factory()->create(['full_name' => 'مريض مباشر']);
    $directVisit = PatientVisit::factory()->create([
        'patient_id' => $directPatient->id,
        'shift_id' => $openShift->id,
        'referring_doctor_id' => null,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    // Payments: visit1 (1,000 Cash), visit2 (500 Card), directVisit (300 Cash - ignored)
    Payment::factory()->create([
        'visit_id' => $visit1->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 1000,
        'payment_method' => PaymentMethod::Cash,
    ]);

    Payment::factory()->create([
        'visit_id' => $visit2->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 500,
        'payment_method' => PaymentMethod::Card,
    ]);

    Payment::factory()->create([
        'visit_id' => $directVisit->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 300,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Refund: visit3 (200 Cash)
    Payment::factory()->create([
        'visit_id' => $visit3->id,
        'shift_id' => $openShift->id,
        'type' => 'refund',
        'amount' => -200,
        'payment_method' => PaymentMethod::Cash,
    ]);

    livewire(DoctorReferralsStatsOverviewWidget::class, [
        'pageFilters' => ['shift_ids' => [$openShift->id]],
    ])
        ->assertOk()
        ->assertSee('إجمالي الإحالات')
        ->assertSee('3') // 3 referral visits (visit1, visit2, visit3)
        ->assertSee('المرضى الجدد من الإحالات')
        ->assertSee('1') // only newPatient1, returningPatient is old
        ->assertSee('إجمالي مدفوعات الإحالات')
        ->assertSee('1,500.00 ج.م') // 1000 + 500
        ->assertSee('نقدي: 1,000 ج.م')
        ->assertSee('بطاقة: 500 ج.م')
        ->assertSee('إجمالي مستردات الإحالات')
        ->assertSee('200.00 ج.م') // 200
        ->assertSee('صافي إيرادات الإحالات')
        ->assertSee('1,300.00 ج.م'); // 1500 - 200 = 1300
});

it('renders doctor referrals table widget with all required columns and view action', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctorA = ReferringDoctor::factory()->create([
        'name' => 'د. سمير صبري',
        'specialization' => 'باطنة',
    ]);

    $doctorB = ReferringDoctor::factory()->create([
        'name' => 'د. منى زكي',
        'specialization' => 'نساء وتوليد',
    ]);

    $newPatientA = Patient::factory()->create(['full_name' => 'مريض سمير']);
    $visitA1 = PatientVisit::factory()->create([
        'patient_id' => $newPatientA->id,
        'shift_id' => $shift->id,
        'referring_doctor_id' => $doctorA->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    $visitA2 = PatientVisit::factory()->create([
        'patient_id' => $newPatientA->id,
        'shift_id' => $shift->id,
        'referring_doctor_id' => $doctorA->id,
        'status' => VisitStatus::Waiting,
        'visit_date' => now(),
    ]);

    $newPatientB = Patient::factory()->create(['full_name' => 'مريض منى']);
    $visitB1 = PatientVisit::factory()->create([
        'patient_id' => $newPatientB->id,
        'shift_id' => $shift->id,
        'referring_doctor_id' => $doctorB->id,
        'status' => VisitStatus::Cancelled,
        'visit_date' => now(),
    ]);

    Payment::factory()->create([
        'visit_id' => $visitA1->id,
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 800,
        'payment_method' => PaymentMethod::Cash,
    ]);

    Payment::factory()->create([
        'visit_id' => $visitB1->id,
        'shift_id' => $shift->id,
        'type' => 'refund',
        'amount' => -150,
        'payment_method' => PaymentMethod::Cash,
    ]);

    livewire(DoctorReferralsTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$doctorA, $doctorB])
        ->assertSee('د. سمير صبري')
        ->assertSee('د. منى زكي')
        ->assertSee('800.00')
        ->assertSee('150.00')
        ->assertSee('66.7%')
        ->assertSee('33.3%')
        ->assertTableActionExists('view');
});

it('renders doctor referrals chart widget with bar dataset', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctor = ReferringDoctor::factory()->create(['name' => 'د. طارق']);
    $patient = Patient::factory()->create();

    PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'referring_doctor_id' => $doctor->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    livewire(DoctorReferralsChartWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk();
});

it('can access doctor referral details page and renders patients and visits table widgets', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctor = ReferringDoctor::factory()->create([
        'name' => 'د. عصام الدين',
        'specialization' => 'عظام',
        'phone' => '01012345678',
    ]);

    $patient = Patient::factory()->create(['full_name' => 'أحمد سمير']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'referring_doctor_id' => $doctor->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    Payment::factory()->create([
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 450,
        'payment_method' => PaymentMethod::Cash,
    ]);

    get(DoctorReferralDetailsReport::getUrl(['record' => $doctor->id]))
        ->assertOk()
        ->assertSee('د. عصام الدين')
        ->assertSee('عظام');

    livewire(App\Filament\Pages\Reports\Widgets\DoctorReferralStatsWidget::class, [
        'doctorId' => $doctor->id,
        'shiftIds' => [$shift->id],
    ])
        ->assertOk()
        ->assertSee('إجمالي الإحالات')
        ->assertSee('1')
        ->assertSee('المرضى المحالين')
        ->assertSee('إجمالي المدفوعات')
        ->assertSee('450.00 ج.م')
        ->assertSee('صافي الإيرادات')
        ->assertSee('450.00 ج.م');

    livewire(DoctorReferralPatientsTableWidget::class, [
        'doctorId' => $doctor->id,
        'shiftIds' => [$shift->id],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$patient])
        ->assertSee('450.00');

    livewire(DoctorReferralVisitsTableWidget::class, [
        'doctorId' => $doctor->id,
        'shiftIds' => [$shift->id],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$visit])
        ->assertSee('450.00')
        ->assertTableActionExists('viewDetails');
});
