<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\DoctorReferralsReport;
use App\Filament\Pages\Reports\FinancialReport;
use App\Filament\Pages\Reports\OutstandingDebtsReport;
use App\Filament\Pages\Reports\ServicesReport;
use App\Filament\Widgets\ClinicStatsOverviewWidget;
use App\Filament\Widgets\IncomeExpensesChartWidget;
use App\Filament\Widgets\TodayQueueTableWidget;
use App\Filament\Widgets\VisitsTrendChartWidget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\ReferringDoctor;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use App\Models\User;
use App\Models\VisitService;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render clinic stats overview widget', function () {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'visit_date' => now(),
        'status' => VisitStatus::Waiting,
    ]);

    livewire(ClinicStatsOverviewWidget::class)
        ->assertOk();
});

it('can render visits trend chart widget', function () {
    livewire(VisitsTrendChartWidget::class)
        ->assertOk();
});

it('can render income vs expenses chart widget', function () {
    livewire(IncomeExpensesChartWidget::class)
        ->assertOk();
});

it('can render today queue table widget', function () {
    $patient = Patient::factory()->create(['full_name' => 'محمد أحمد']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'visit_date' => now(),
        'status' => VisitStatus::Waiting,
    ]);

    livewire(TodayQueueTableWidget::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$visit]);
});

it('can render financial report page with data breakdown', function () {
    $category = ExpenseCategory::factory()->create(['name' => 'مستلزمات طبية']);
    Expense::factory()->create([
        'expense_category_id' => $category->id,
        'amount' => 450.00,
        'created_by' => $this->user->id,
        'created_at' => now(),
    ]);

    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    Payment::factory()->create([
        'visit_id' => $visit->id,
        'amount' => 1200.00,
        'payment_method' => PaymentMethod::Cash,
        'paid_at' => now(),
    ]);

    livewire(FinancialReport::class)
        ->assertOk()
        ->assertSee('1,200.00')
        ->assertSee('450.00')
        ->assertSee('مستلزمات طبية');
});

it('can render doctor referrals report page', function () {
    $doctor = ReferringDoctor::factory()->create(['name' => 'د. خالد إبراهيم']);
    $patient = Patient::factory()->create();
    PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'referring_doctor_id' => $doctor->id,
        'visit_date' => now(),
    ]);

    livewire(DoctorReferralsReport::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$doctor]);
});

it('can render services performance report page', function () {
    $category = ServiceCategory::factory()->create(['name' => 'أشعة']);
    $service = Service::factory()->create([
        'category_id' => $category->id,
        'name' => 'أشعة سونار على البطن',
        'base_price' => 350.00,
    ]);

    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'total' => 350.00,
    ]);

    livewire(ServicesReport::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$service]);
});

it('can render outstanding debts report page', function () {
    $patient = Patient::factory()->create(['full_name' => 'أحمد محمود']);
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $invoice = Invoice::create([
        'visit_id' => $visit->id,
        'invoice_number' => 'INV-TEST-001',
        'status' => InvoiceStatus::PartiallyPaid,
        'subtotal' => 1000.00,
        'discount_total' => 0.0,
        'total_amount' => 1000.00,
        'paid_amount' => 600.00,
        'remaining_amount' => 400.00,
    ]);

    livewire(OutstandingDebtsReport::class)
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$invoice]);
});
