<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\ReferringDoctor;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

test('unauthenticated users are redirected from reception', function () {
    auth()->logout();
    $response = $this->get('/reception');
    $response->assertRedirect('/login');
});

test('authenticated users can view the reception page with inertia props', function () {
    $response = $this->get('/reception');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Reception/Index')
        ->has('visits.data')
        ->has('todayExpenses')
        ->has('services')
        ->has('expenseCategories')
        ->has('filters')
    );
});

test('can lookup patient by phone number', function () {
    $patient = Patient::factory()->create([
        'phone' => '01099887766',
        'full_name' => 'ياسر محمد',
        'birth_date' => '1995-05-15',
    ]);

    $response = $this->getJson('/reception/patient-by-phone?phone=01099887766');

    $response->assertOk();
    $response->assertJson([
        'found' => true,
        'patient' => [
            'id' => $patient->id,
            'full_name' => 'ياسر محمد',
            'phone' => '01099887766',
            'birth_date' => '1995-05-15',
        ],
    ]);
});

test('lookup returns not found for non existing phone', function () {
    $response = $this->getJson('/reception/patient-by-phone?phone=01000000000');

    $response->assertOk();
    $response->assertJson([
        'found' => false,
        'patient' => null,
    ]);
});

test('can search patients by query or phone', function () {
    $patient = Patient::factory()->create([
        'full_name' => 'كمال الشناوي',
        'phone' => '01234567890',
        'birth_date' => '1988-10-20',
    ]);

    $response = $this->postJson('/reception/search-patients', [
        'name' => 'كمال',
    ]);

    $response->assertOk();
    $response->assertJsonFragment([
        'full_name' => 'كمال الشناوي',
        'phone' => '01234567890',
    ]);
});

test('reception can create a new patient and store visit with service and initial payment', function () {
    $service = Service::factory()->create([
        'base_price' => 300.00,
        'is_active' => true,
    ]);

    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $doctor = ReferringDoctor::factory()->create();

    $response = $this->post('/reception/visits', [
        'full_name' => 'أحمد إبراهيم',
        'phone' => '01122334455',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'address' => 'القاهرة',
        'visit_date' => now()->format('Y-m-d H:i:s'),
        'referring_doctor_id' => $doctor->id,
        'services' => [
            [
                'service_id' => $service->id,
                'quantity' => 1,
                'discount_type' => 'fixed',
                'discount_value' => 50,
            ],
        ],
        'paid_amount' => 250.00,
        'payment_method' => 'cash',
        'payment_notes' => 'دفعة حجز أولى',
    ]);

    $response->assertRedirect('/reception');

    assertDatabaseHas(Patient::class, [
        'phone' => '01122334455',
        'full_name' => 'أحمد إبراهيم',
    ]);

    $patient = Patient::where('phone', '01122334455')->first();
    expect($patient->birth_date?->format('Y-m-d'))->toBe('1990-01-01');
    assertDatabaseHas(PatientVisit::class, [
        'patient_id' => $patient->id,
        'referring_doctor_id' => $doctor->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visit = PatientVisit::where('patient_id', $patient->id)->first();
    expect($visit->invoice)->not->toBeNull();
    expect((float) $visit->invoice->total_amount)->toEqual(250.00);
    expect((float) $visit->invoice->paid_amount)->toEqual(250.00);
    expect((float) $visit->invoice->remaining_amount)->toEqual(0.00);
});

test('completing a visit requires settling due amounts first', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $service = Service::factory()->create([
        'base_price' => 500.00,
        'is_active' => true,
    ]);

    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visit->visitServices()->create([
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 500.00,
        'discount_value' => 0,
        'subtotal' => 500.00,
        'total' => 500.00,
    ]);

    app(App\Services\InvoiceService::class)->syncInvoice($visit);

    // Try to complete visit while remaining amount is 500 EGP
    $response = $this->post("/reception/visits/{$visit->id}/complete");

    $response->assertSessionHas('error');
    expect($visit->fresh()->status)->toBe(VisitStatus::Waiting);

    // Pay the remaining amount
    $this->post("/reception/visits/{$visit->id}/payments", [
        'amount' => 500.00,
        'payment_method' => 'cash',
        'notes' => 'سداد كامل المتبقي',
    ]);

    // Now complete the visit
    $completeResponse = $this->post("/reception/visits/{$visit->id}/complete");
    $completeResponse->assertSessionHas('success');
    expect($visit->fresh()->status)->toBe(VisitStatus::Completed);
});

test('reception can record an expense and view in today expenses', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $category = ExpenseCategory::factory()->create(['name' => 'نظافة وضيافة']);

    $response = $this->post('/reception/expenses', [
        'expense_category_id' => $category->id,
        'amount' => 150.50,
        'notes' => 'شراء مستلزمات نظافة',
    ]);

    $response->assertRedirect();
    assertDatabaseHas(Expense::class, [
        'expense_category_id' => $category->id,
        'shift_id' => $shift->id,
        'amount' => 150.50,
        'notes' => 'شراء مستلزمات نظافة',
        'created_by' => $this->user->id,
    ]);

    $viewResponse = $this->get('/reception');
    $viewResponse->assertOk();
    $viewResponse->assertInertia(fn (Assert $page) => $page
        ->component('Reception/Index')
        ->where('todayExpensesTotal', 150.5)
        ->has('todayExpenses', 1)
        ->where('todayExpenses.0.amount', 150.5)
    );
});

test('reception can delete an expense', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $expense = Expense::factory()->create([
        'shift_id' => $shift->id,
        'created_by' => $this->user->id,
        'amount' => 75.00,
    ]);

    $response = $this->delete("/reception/expenses/{$expense->id}");
    $response->assertRedirect();

    assertDatabaseMissing(Expense::class, [
        'id' => $expense->id,
    ]);
});

test('reception can update an expense when shift is open', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $category = ExpenseCategory::factory()->create();
    $expense = Expense::factory()->create([
        'shift_id' => $shift->id,
        'created_by' => $this->user->id,
        'amount' => 50.00,
        'notes' => 'Old note',
    ]);

    $response = $this->put("/reception/expenses/{$expense->id}", [
        'expense_category_id' => $category->id,
        'amount' => 120.00,
        'notes' => 'Updated note',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    assertDatabaseHas(Expense::class, [
        'id' => $expense->id,
        'expense_category_id' => $category->id,
        'amount' => 120.00,
        'notes' => 'Updated note',
    ]);
});

test('reception cannot update an expense when shift is closed', function () {
    $closedShift = Shift::factory()->closed()->create();
    $category = ExpenseCategory::factory()->create();
    $expense = Expense::factory()->create([
        'shift_id' => $closedShift->id,
        'created_by' => $this->user->id,
        'amount' => 50.00,
    ]);

    $response = $this->put("/reception/expenses/{$expense->id}", [
        'expense_category_id' => $category->id,
        'amount' => 200.00,
    ]);

    $response->assertSessionHas('error');
    assertDatabaseHas(Expense::class, [
        'id' => $expense->id,
        'amount' => 50.00,
    ]);
});

test('reception cannot delete an expense when shift is closed', function () {
    $closedShift = Shift::factory()->closed()->create();
    $expense = Expense::factory()->create([
        'shift_id' => $closedShift->id,
        'created_by' => $this->user->id,
        'amount' => 75.00,
    ]);

    $response = $this->delete("/reception/expenses/{$expense->id}");
    $response->assertSessionHas('error');

    assertDatabaseHas(Expense::class, [
        'id' => $expense->id,
    ]);
});

test('reception can update an existing waiting visit and re-sync invoice', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create([
        'full_name' => 'Original Name',
        'phone' => '01111111111',
    ]);

    $service1 = Service::factory()->create(['base_price' => 200.00]);
    $service2 = Service::factory()->create(['base_price' => 350.00]);
    $doctor = ReferringDoctor::factory()->create(['name' => 'د. أحمد علي']);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
        'notes' => 'Original note',
    ]);

    $response = $this->put("/reception/visits/{$visit->id}", [
        'patient_id' => $patient->id,
        'full_name' => 'Updated Name',
        'phone' => '01222222222',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'address' => 'New Address',
        'patient_notes' => 'Patient updated note',
        'visit_date' => '2026-09-24 10:00:00',
        'referring_doctor_id' => $doctor->id,
        'notes' => 'Updated visit note',
        'services' => [
            [
                'service_id' => $service2->id,
                'quantity' => 2,
                'discount_type' => 'fixed',
                'discount_value' => 50,
                'selected_options' => [],
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    assertDatabaseHas(Patient::class, [
        'id' => $patient->id,
        'full_name' => 'Updated Name',
        'phone' => '01222222222',
    ]);

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'referring_doctor_id' => $doctor->id,
        'notes' => 'Updated visit note',
    ]);

    $updatedVisit = $visit->fresh(['visitServices', 'invoice']);
    expect($updatedVisit->visitServices)->toHaveCount(1)
        ->and($updatedVisit->visitServices->first()->service_id)->toBe($service2->id)
        ->and($updatedVisit->visitServices->first()->quantity)->toBe(2)
        ->and((float) $updatedVisit->visitServices->first()->total)->toBe(650.0);

    // Invoice subtotal: 350 * 2 = 700, discount 50 => total 650
    expect((float) $updatedVisit->invoice->total_amount)->toBe(650.0);
});

test('reception cannot update completed visit', function () {
    $patient = Patient::factory()->create();
    $service = Service::factory()->create(['base_price' => 100.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'status' => VisitStatus::Completed,
    ]);

    $response = $this->put("/reception/visits/{$visit->id}", [
        'full_name' => 'Test Name',
        'phone' => '01234567890',
        'services' => [
            [
                'service_id' => $service->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHas('error');
});

test('reception cancel visit automatically refunds all recorded payments and cancels services', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $service = Service::factory()->create(['base_price' => 350.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $vs = App\Models\VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 350.00,
        'discount_type' => App\Enums\DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 350.00,
        'total' => 350.00,
        'status' => App\Enums\VisitServiceStatus::Pending,
    ]);

    $invoice = app(App\Services\InvoiceService::class)->syncInvoice($visit);

    App\Models\Payment::create([
        'visit_id' => $visit->id,
        'invoice_id' => $invoice->id,
        'type' => 'payment',
        'amount' => 350.00,
        'payment_method' => App\Enums\PaymentMethod::Cash,
        'notes' => 'تحصيل كاش عند التسجيل',
    ]);

    $response = $this->post("/reception/visits/{$visit->id}/cancel");
    $response->assertRedirect();
    $response->assertSessionHas('success', 'تم إلغاء الزيارة واسترداد المدفوعات تلقائياً.');

    $refreshed = $visit->fresh(['payments', 'visitServices', 'invoice']);
    expect($refreshed->status)->toBe(VisitStatus::Cancelled)
        ->and($refreshed->visitServices->first()->status)->toBe(App\Enums\VisitServiceStatus::Cancelled);

    $refundPayment = $refreshed->payments->firstWhere('type', 'refund');
    expect($refundPayment)->not->toBeNull()
        ->and((float) $refundPayment->amount)->toBe(-350.0);

    expect((float) $refreshed->invoice->paid_amount)->toBe(0.0)
        ->and((float) $refreshed->invoice->remaining_amount)->toBe(0.0)
        ->and($refreshed->invoice->status)->toBe(App\Enums\InvoiceStatus::Cancelled);
});

test('storing visit fails with validation error when user has no active shift', function () {
    $service = Service::factory()->create([
        'base_price' => 200.00,
        'is_active' => true,
    ]);

    // Ensure user has NO active shifts
    Shift::query()->where('user_id', $this->user->id)->delete();

    $response = $this->post('/reception/visits', [
        'full_name' => 'خالد عبد الله',
        'phone' => '01511223344',
        'services' => [
            [
                'service_id' => $service->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['shift']);
    assertDatabaseMissing(Patient::class, [
        'phone' => '01511223344',
    ]);
});

test('reception user can start a shift with opening balance', function () {
    Shift::query()->where('user_id', $this->user->id)->delete();

    $response = $this->post('/reception/shifts/start', [
        'opening_balance' => 500.00,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    assertDatabaseHas(Shift::class, [
        'user_id' => $this->user->id,
        'opening_balance' => 500.00,
        'status' => ShiftStatus::Open->value,
    ]);

    $shift = Shift::query()->where('user_id', $this->user->id)->where('status', ShiftStatus::Open)->first();
    expect($shift)->not->toBeNull()
        ->and($shift->opened_at)->not->toBeNull();
});

test('reception user cannot start a shift if one is already active', function () {
    Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $response = $this->post('/reception/shifts/start', [
        'opening_balance' => 100.00,
    ]);

    $response->assertSessionHas('error', 'توجد وردية مفتوحة بالفعل.');
});

test('reception user can close an active shift with closing balance', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opening_balance' => 200.00,
        'opened_at' => now()->subHours(6),
    ]);

    $response = $this->post('/reception/shifts/close', [
        'closing_balance' => 1500.00,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $shift->refresh();
    expect($shift->status)->toBe(ShiftStatus::Closed)
        ->and((float) $shift->closing_balance)->toBe(1500.00)
        ->and((float) $shift->actual_cash)->toBe(1500.00)
        ->and($shift->closed_at)->not->toBeNull();
});

test('closing shift fails if no active shift exists', function () {
    Shift::query()->where('user_id', $this->user->id)->delete();

    $response = $this->post('/reception/shifts/close', [
        'closing_balance' => 1000.00,
    ]);

    $response->assertSessionHas('error', 'لا توجد وردية مفتوحة لإغلاقها.');
});

test('today visits and expenses in reception index are scoped to the current active shift', function () {
    $previousShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Closed,
        'opened_at' => now()->subHours(8),
        'closed_at' => now()->subHours(2),
    ]);

    $activeShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now()->subHour(),
    ]);

    $patient1 = Patient::factory()->create(['full_name' => 'Active Shift Patient']);
    $visitInActiveShift = PatientVisit::factory()->create([
        'patient_id' => $patient1->id,
        'shift_id' => $activeShift->id,
        'visit_date' => now(),
    ]);

    $patient2 = Patient::factory()->create(['full_name' => 'Previous Shift Patient']);
    $visitInPreviousShift = PatientVisit::factory()->create([
        'patient_id' => $patient2->id,
        'shift_id' => $previousShift->id,
        'visit_date' => now(),
    ]);

    $category = ExpenseCategory::factory()->create(['name' => 'مستلزمات']);

    $expenseInActiveShift = Expense::factory()->create([
        'shift_id' => $activeShift->id,
        'expense_category_id' => $category->id,
        'amount' => 120.00,
        'created_by' => $this->user->id,
        'created_at' => now(),
    ]);

    $expenseInPreviousShift = Expense::factory()->create([
        'shift_id' => $previousShift->id,
        'expense_category_id' => $category->id,
        'amount' => 80.00,
        'created_by' => $this->user->id,
        'created_at' => now(),
    ]);

    $response = $this->get('/reception');
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Reception/Index')
        ->where('todayExpensesTotal', 120)
        ->has('todayExpenses', 1)
        ->where('todayExpenses.0.id', $expenseInActiveShift->id)
        ->has('visits.data', 1)
        ->where('visits.data.0.id', $visitInActiveShift->id)
    );

    $allVisitsResponse = $this->get('/reception?date_filter=all');
    $allVisitsResponse->assertOk();
    $allVisitsResponse->assertInertia(fn (Assert $page) => $page
        ->component('Reception/Index')
        ->has('visits.data', 2)
    );
});

test('storing expense fails with validation error when user has no active shift', function () {
    $category = ExpenseCategory::factory()->create();

    // Ensure user has NO active shifts
    Shift::query()->where('user_id', $this->user->id)->delete();

    $response = $this->post('/reception/expenses', [
        'expense_category_id' => $category->id,
        'amount' => 100.00,
    ]);

    $response->assertSessionHasErrors(['shift']);
    assertDatabaseMissing(Expense::class, [
        'expense_category_id' => $category->id,
        'amount' => 100.00,
    ]);
});

test('reception user can issue refund against paid amount when shift is open', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $service = Service::factory()->create(['base_price' => 500.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visit->visitServices()->create([
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 500.00,
        'discount_value' => 0,
        'subtotal' => 500.00,
        'total' => 500.00,
    ]);

    $invoice = app(App\Services\InvoiceService::class)->syncInvoice($visit);

    // Initial payment of 500 EGP
    $this->post("/reception/visits/{$visit->id}/payments", [
        'amount' => 500.00,
        'payment_method' => 'cash',
        'notes' => 'دفع كاش',
    ]);

    expect((float) $invoice->fresh()->paid_amount)->toBe(500.00);

    // Issue refund of 200 EGP
    $refundResponse = $this->post("/reception/visits/{$visit->id}/refund", [
        'amount' => 200.00,
        'payment_method' => 'cash',
        'notes' => 'استرداد جزئي بناء على طلب المريض',
    ]);

    $refundResponse->assertRedirect();
    $refundResponse->assertSessionHas('success');

    $refreshedInvoice = $invoice->fresh();
    expect((float) $refreshedInvoice->paid_amount)->toBe(300.00)
        ->and((float) $refreshedInvoice->remaining_amount)->toBe(200.00);

    assertDatabaseHas(App\Models\Payment::class, [
        'visit_id' => $visit->id,
        'type' => 'refund',
        'amount' => -200.00,
        'shift_id' => $shift->id,
    ]);
});

test('reception user cannot issue refund exceeding paid amount', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $service = Service::factory()->create(['base_price' => 300.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visit->visitServices()->create([
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 300.00,
        'discount_value' => 0,
        'subtotal' => 300.00,
        'total' => 300.00,
    ]);

    app(App\Services\InvoiceService::class)->syncInvoice($visit);

    // Pay 300 EGP
    $this->post("/reception/visits/{$visit->id}/payments", [
        'amount' => 300.00,
        'payment_method' => 'cash',
    ]);

    // Attempt refund of 500 EGP (exceeds paid)
    $response = $this->post("/reception/visits/{$visit->id}/refund", [
        'amount' => 500.00,
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHas('error');
});

test('reception user cannot issue refund when no shift is open', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $invoice = app(App\Services\InvoiceService::class)->syncInvoice($visit);

    $this->post("/reception/visits/{$visit->id}/payments", [
        'amount' => 100.00,
        'payment_method' => 'cash',
    ]);

    // Close shift
    $shift->update(['status' => ShiftStatus::Closed, 'closed_at' => now()]);

    $response = $this->post("/reception/visits/{$visit->id}/refund", [
        'amount' => 50.00,
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHas('error');
});

test('updating visit preserves existing services with reports and does not delete reports', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $service1 = Service::factory()->create(['name' => 'فحص سونار', 'base_price' => 300.00]);
    $service2 = Service::factory()->create(['name' => 'تحليل دم', 'base_price' => 150.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visitService1 = $visit->visitServices()->create([
        'service_id' => $service1->id,
        'quantity' => 1,
        'unit_price' => 300.00,
        'discount_value' => 0,
        'subtotal' => 300.00,
        'total' => 300.00,
    ]);

    // Create a medical report attached to service1
    $report = App\Models\Report::create([
        'visit_service_id' => $visitService1->id,
        'user_id' => $this->user->id,
        'title' => 'تقرير فحص السونار',
        'report_text' => 'تقرير طبي فحص السونار سليم تماما',
    ]);

    // Now update the visit: keep service1 (providing its id) and add service2
    $response = $this->put("/reception/visits/{$visit->id}", [
        'patient_id' => $patient->id,
        'full_name' => $patient->full_name,
        'phone' => $patient->phone,
        'visit_date' => now()->format('Y-m-d H:i:s'),
        'services' => [
            [
                'id' => $visitService1->id,
                'service_id' => $service1->id,
                'quantity' => 1,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ],
            [
                'service_id' => $service2->id,
                'quantity' => 1,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify report was NOT deleted and is still attached to the visitService
    expect(App\Models\Report::find($report->id))->not->toBeNull()
        ->and($visit->fresh()->visitServices)->toHaveCount(2);
});

test('updating visit fails if trying to remove a service that has a report', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $patient = Patient::factory()->create();
    $service1 = Service::factory()->create(['name' => 'فحص إيكو', 'base_price' => 400.00]);
    $service2 = Service::factory()->create(['name' => 'فحص دم', 'base_price' => 100.00]);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visitService1 = $visit->visitServices()->create([
        'service_id' => $service1->id,
        'quantity' => 1,
        'unit_price' => 400.00,
        'discount_value' => 0,
        'subtotal' => 400.00,
        'total' => 400.00,
    ]);

    // Attach report to service1
    App\Models\Report::create([
        'visit_service_id' => $visitService1->id,
        'user_id' => $this->user->id,
        'title' => 'تقرير إيكو',
        'report_text' => 'تقرير طبي للإيكو',
    ]);

    // Try to update visit by omitting service1 (removing it) and only sending service2
    $response = $this->put("/reception/visits/{$visit->id}", [
        'patient_id' => $patient->id,
        'full_name' => $patient->full_name,
        'phone' => $patient->phone,
        'visit_date' => now()->format('Y-m-d H:i:s'),
        'services' => [
            [
                'service_id' => $service2->id,
                'quantity' => 1,
                'discount_type' => 'fixed',
                'discount_value' => 0,
            ],
        ],
    ]);

    $response->assertSessionHas('error');
    // Ensure service1 was NOT deleted
    assertDatabaseHas(App\Models\VisitService::class, [
        'id' => $visitService1->id,
    ]);
});
