<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\ShiftsReport;
use App\Filament\Pages\Reports\Widgets\ShiftExpensesTableWidget;
use App\Filament\Pages\Reports\Widgets\ShiftFinancialChartWidget;
use App\Filament\Pages\Reports\Widgets\ShiftPaymentsTableWidget;
use App\Filament\Pages\Reports\Widgets\ShiftRefundsTableWidget;
use App\Filament\Pages\Reports\Widgets\ShiftStatsOverviewWidget;
use App\Filament\Pages\Reports\Widgets\ShiftVisitsChartWidget;
use App\Filament\Pages\Reports\Widgets\ShiftVisitsTableWidget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\ReferringDoctor;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can access the shifts report page via url', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    get(ShiftsReport::getUrl())
        ->assertOk();
});

it('auto selects currently opened shift if exists', function (): void {
    $closedShift = Shift::factory()->create([
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

    livewire(ShiftsReport::class)
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

    livewire(ShiftsReport::class)
        ->assertOk()
        ->assertSet('filters.mode', 'period')
        ->assertSet('filters.preset', 'today');
});

it('resolves stats in period mode using ShiftFilter', function (): void {
    Carbon::setTestNow('2026-09-25 14:00:00');

    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => Carbon::parse('2026-09-25 09:00:00'),
    ]);

    $patient = Patient::factory()->create();
    PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    livewire(ShiftStatsOverviewWidget::class, [
        'pageFilters' => [
            'mode' => 'period',
            'preset' => 'today',
        ],
    ])
        ->assertOk()
        ->assertSee('إجمالي الزيارات')
        ->assertSee('1');

    Carbon::setTestNow();
});

it('renders the 9 flash cards with accurate statistics and breakdown', function (): void {
    $openShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $referringDoctor = ReferringDoctor::factory()->create(['name' => 'د. خالد']);

    // 1 new patient with 1 completed visit
    $newPatient = Patient::factory()->create(['full_name' => 'مريض جديد']);
    $completedVisit = PatientVisit::factory()->create([
        'patient_id' => $newPatient->id,
        'shift_id' => $openShift->id,
        'referring_doctor_id' => $referringDoctor->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    // 1 returning patient (had a previous visit in a closed shift)
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

    // Returning patient has a waiting visit in the current shift
    $waitingVisit = PatientVisit::factory()->create([
        'patient_id' => $returningPatient->id,
        'shift_id' => $openShift->id,
        'status' => VisitStatus::Waiting,
        'visit_date' => now(),
    ]);

    // 1 cancelled visit
    $cancelledVisit = PatientVisit::factory()->create([
        'patient_id' => $newPatient->id,
        'shift_id' => $openShift->id,
        'status' => VisitStatus::Cancelled,
        'visit_date' => now(),
    ]);

    // Payments: 1000 Cash, 500 Card
    Payment::factory()->create([
        'visit_id' => $completedVisit->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 1000,
        'payment_method' => PaymentMethod::Cash,
    ]);

    Payment::factory()->create([
        'visit_id' => $waitingVisit->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 500,
        'payment_method' => PaymentMethod::Card,
    ]);

    // Refund: 200 Cash
    Payment::factory()->create([
        'visit_id' => $cancelledVisit->id,
        'shift_id' => $openShift->id,
        'type' => 'refund',
        'amount' => -200,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Expense: 300 EGP
    $category = ExpenseCategory::factory()->create(['name' => 'مستلزمات']);
    Expense::factory()->create([
        'expense_category_id' => $category->id,
        'shift_id' => $openShift->id,
        'amount' => 300,
        'created_by' => $this->user->id,
    ]);

    livewire(ShiftStatsOverviewWidget::class, [
        'pageFilters' => ['shift_ids' => [$openShift->id]],
    ])
        ->assertOk()
        ->assertSee('إجمالي الزيارات')
        ->assertSee('3') // total visits in shift
        ->assertSee('الزيارات المكتملة')
        ->assertSee('1') // completed
        ->assertSee('زيارات في الانتظار')
        ->assertSee('1') // waiting
        ->assertSee('الزيارات الملغاة')
        ->assertSee('1') // cancelled
        ->assertSee('إجمالي المدفوعات')
        ->assertSee('1,500.00 ج.م')
        ->assertSee('نقدي: 1,000 ج.م')
        ->assertSee('بطاقة: 500 ج.م')
        ->assertSee('إجمالي المستردات')
        ->assertSee('200.00 ج.م')
        ->assertSee('إجمالي المصروفات')
        ->assertSee('300.00 ج.م')
        ->assertSee('صافي الإيراد')
        ->assertSee('1,000.00 ج.م')
        ->assertSee('المرضى الجدد')
        ->assertSee('1') // only newPatient, not returningPatient
        ->assertSee('الإحالات الجديدة')
        ->assertSee('1'); // referringDoctor
});

it('renders shift visits table widget with view details action', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $patient = Patient::factory()->create(['full_name' => 'سارة محمود']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    livewire(ShiftVisitsTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$visit])
        ->assertTableActionExists('viewDetails');
});

it('renders shift expenses table widget with records', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $category = ExpenseCategory::factory()->create(['name' => 'شاي وضيافة']);
    $expense = Expense::factory()->create([
        'expense_category_id' => $category->id,
        'shift_id' => $shift->id,
        'amount' => 75,
        'created_by' => $this->user->id,
        'notes' => 'ضيافة الاستقبال',
    ]);

    livewire(ShiftExpensesTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$expense]);
});

it('renders financial and visits chart widgets', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    livewire(ShiftFinancialChartWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])->assertOk();

    livewire(ShiftVisitsChartWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])->assertOk();
});

it('calculates net revenue accurately including negative amounts', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    // Payment: 100
    Payment::factory()->create([
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 100,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Refund: 50
    Payment::factory()->create([
        'shift_id' => $shift->id,
        'type' => 'refund',
        'amount' => -50,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Expense: 200
    $category = ExpenseCategory::factory()->create();
    Expense::factory()->create([
        'expense_category_id' => $category->id,
        'shift_id' => $shift->id,
        'amount' => 200,
        'created_by' => $this->user->id,
    ]);

    // Net revenue = 100 - 50 - 200 = -150.00
    livewire(ShiftStatsOverviewWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->assertSee('صافي الإيراد')
        ->assertSee('-150.00 ج.م');
});

it('renders shift payments table widget with details and what payment is for', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $patient = Patient::factory()->create(['full_name' => 'أحمد علي']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    $service = App\Models\Service::factory()->create(['name' => 'فحص شامل']);
    App\Models\VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
    ]);

    $payment = Payment::factory()->create([
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 450,
        'payment_method' => PaymentMethod::Cash,
        'notes' => 'دفعة نقدية بالكامل',
    ]);

    livewire(ShiftPaymentsTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$payment])
        ->assertSee('أحمد علي')
        ->assertSee('فحص شامل')
        ->assertSee('450.00')
        ->assertTableActionExists('viewVisitDetails');
});

it('renders shift refunds table widget with details and what refund is for', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $patient = Patient::factory()->create(['full_name' => 'منى السيد']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Cancelled,
        'visit_date' => now(),
    ]);

    $refund = Payment::factory()->create([
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'type' => 'refund',
        'amount' => -250,
        'payment_method' => PaymentMethod::Card,
        'notes' => 'استرداد نقدي لإلغاء الموعد',
    ]);

    livewire(ShiftRefundsTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$refund])
        ->assertSee('منى السيد')
        ->assertSee('استرداد نقدي لإلغاء الموعد')
        ->assertSee('250.00')
        ->assertTableActionExists('viewVisitDetails');
});
