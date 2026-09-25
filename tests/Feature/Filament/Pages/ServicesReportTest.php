<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\VisitServiceStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\Reports\ServiceDetailsReport;
use App\Filament\Pages\Reports\ServicesReport;
use App\Filament\Pages\Reports\Widgets\ServiceDetailStatsWidget;
use App\Filament\Pages\Reports\Widgets\ServicePerformanceChartWidget;
use App\Filament\Pages\Reports\Widgets\ServicesTableWidget;
use App\Filament\Pages\Reports\Widgets\ServiceStatsOverviewWidget;
use App\Filament\Pages\Reports\Widgets\ServiceVisitsTableWidget;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Shift;
use App\Models\User;
use App\Models\VisitService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can access the services report page via url', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    get(ServicesReport::getUrl())
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

    livewire(ServicesReport::class)
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

    livewire(ServicesReport::class)
        ->assertOk()
        ->assertSet('filters.mode', 'period')
        ->assertSet('filters.preset', 'today');
});

it('calculates top performed service and top revenue service flash cards correctly', function (): void {
    $openShift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $category = ServiceCategory::factory()->create(['name' => 'باطنة']);
    $serviceA = Service::factory()->create([
        'category_id' => $category->id,
        'name' => 'رسم قلب عادي',
        'base_price' => 100,
    ]);
    $serviceB = Service::factory()->create([
        'category_id' => $category->id,
        'name' => 'سونار بطن وأحشاء',
        'base_price' => 800,
    ]);

    $patient1 = Patient::factory()->create(['full_name' => 'مريض أ']);
    $patient2 = Patient::factory()->create(['full_name' => 'مريض ب']);

    // Visit 1: Completed, Service A (qty = 3), total = 300, payment = 300
    $visit1 = PatientVisit::factory()->create([
        'patient_id' => $patient1->id,
        'shift_id' => $openShift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);
    VisitService::factory()->create([
        'visit_id' => $visit1->id,
        'service_id' => $serviceA->id,
        'quantity' => 3,
        'unit_price' => 100,
        'subtotal' => 300,
        'total' => 300,
        'status' => VisitServiceStatus::Completed,
    ]);
    Payment::factory()->create([
        'visit_id' => $visit1->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 300,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Visit 2: Completed, Service B (qty = 1), total = 800, payment = 800
    $visit2 = PatientVisit::factory()->create([
        'patient_id' => $patient2->id,
        'shift_id' => $openShift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);
    VisitService::factory()->create([
        'visit_id' => $visit2->id,
        'service_id' => $serviceB->id,
        'quantity' => 1,
        'unit_price' => 800,
        'subtotal' => 800,
        'total' => 800,
        'status' => VisitServiceStatus::Completed,
    ]);
    Payment::factory()->create([
        'visit_id' => $visit2->id,
        'shift_id' => $openShift->id,
        'type' => 'payment',
        'amount' => 800,
        'payment_method' => PaymentMethod::Cash,
    ]);

    // Visit 3: Cancelled, Service A (qty = 1), refund = 50
    $visit3 = PatientVisit::factory()->create([
        'patient_id' => $patient1->id,
        'shift_id' => $openShift->id,
        'status' => VisitStatus::Cancelled,
        'visit_date' => now(),
    ]);
    VisitService::factory()->create([
        'visit_id' => $visit3->id,
        'service_id' => $serviceA->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'total' => 100,
        'status' => VisitServiceStatus::Cancelled,
    ]);
    Payment::factory()->create([
        'visit_id' => $visit3->id,
        'shift_id' => $openShift->id,
        'type' => 'refund',
        'amount' => -50,
        'payment_method' => PaymentMethod::Cash,
    ]);

    livewire(ServiceStatsOverviewWidget::class, [
        'pageFilters' => ['shift_ids' => [$openShift->id]],
    ])
        ->assertOk()
        ->assertSee('الخدمة الأكثر طلباً')
        ->assertSee('رسم قلب عادي') // 3 performed vs 1 performed
        ->assertSee('الخدمة الأكثر دخلاً')
        ->assertSee('سونار بطن وأحشاء') // 800 net vs 250 net
        ->assertSee('800.00 ج.م')
        ->assertSee('إجمالي الخدمات المنفذة')
        ->assertSee('4 خدمة') // 3 + 1
        ->assertSee('صافي إيرادات الخدمات')
        ->assertSee('1,050.00 ج.م'); // (300 + 800) - 50 = 1050
});

it('renders services table widget with accurate counts, payments, refunds, and percentage', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $category = ServiceCategory::factory()->create(['name' => 'أشعة']);
    $service = Service::factory()->create([
        'category_id' => $category->id,
        'name' => 'أشعة سينية',
        'code' => 'XR-001',
    ]);

    $patient = Patient::factory()->create(['full_name' => 'فاطمة محمد']);

    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 2,
        'unit_price' => 200,
        'subtotal' => 400,
        'total' => 400,
        'status' => VisitServiceStatus::Completed,
    ]);

    Payment::factory()->create([
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 400,
        'payment_method' => PaymentMethod::Cash,
    ]);

    livewire(ServicesTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$service])
        ->assertTableActionExists('view');
});

it('renders service performance chart widget with bar dataset', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $service = Service::factory()->create(['name' => 'تحليل صورة دم كاملة']);
    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
    ]);

    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'status' => VisitServiceStatus::Completed,
    ]);

    livewire(ServicePerformanceChartWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->assertSee('تحليل صورة دم كاملة');
});

it('can access service details drill-down page and loads visits table widget', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $category = ServiceCategory::factory()->create(['name' => 'تحاليل']);
    $service = Service::factory()->create([
        'category_id' => $category->id,
        'name' => 'فحص سكر تراكمي',
        'code' => 'LAB-101',
        'base_price' => 150,
    ]);

    $patient = Patient::factory()->create(['full_name' => 'طارق كمال']);
    $visit = PatientVisit::factory()->create([
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 150,
        'subtotal' => 150,
        'total' => 150,
        'status' => VisitServiceStatus::Completed,
    ]);

    get(ServiceDetailsReport::getUrl(['record' => $service->id, 'shift_ids' => [$shift->id]]))
        ->assertOk()
        ->assertSee('تفاصيل أداء خدمة: فحص سكر تراكمي')
        ->assertSee('تحاليل')
        ->assertSee('LAB-101')
        ->assertSee('150.00 ج.م');

    livewire(ServiceDetailStatsWidget::class, [
        'serviceId' => $service->id,
        'shiftIds' => [$shift->id],
    ])
        ->assertOk()
        ->assertSee('إجمالي الزيارات')
        ->assertSee('1')
        ->assertSee('مرات التنفيذ')
        ->assertSee('1 وحدة')
        ->assertSee('المرضى المستفيدين')
        ->assertSee('إجمالي المدفوعات')
        ->assertSee('صافي الإيراد');

    livewire(ServiceVisitsTableWidget::class, [
        'serviceId' => $service->id,
        'shiftIds' => [$shift->id],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([$visit])
        ->assertTableActionExists('viewDetails');
});

it('handles empty shifts with no services gracefully', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    livewire(ServiceStatsOverviewWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->assertSee('لا توجد بيانات')
        ->assertSee('0.00 ج.م')
        ->assertSee('0 خدمة');

    livewire(ServicesTableWidget::class, [
        'pageFilters' => ['shift_ids' => [$shift->id]],
    ])
        ->assertOk()
        ->loadTable()
        ->assertCanSeeTableRecords([]);
});

it('accurately allocates proportional payments for multi-service visits', function (): void {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $service1 = Service::factory()->create(['name' => 'خدمة أولى']);
    $service2 = Service::factory()->create(['name' => 'خدمة ثانية']);

    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
        'visit_date' => now(),
    ]);

    // Service 1: 100 EGP (25%), Service 2: 300 EGP (75%) -> Total 400 EGP
    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service1->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'total' => 100,
        'status' => VisitServiceStatus::Completed,
    ]);

    VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service2->id,
        'quantity' => 1,
        'unit_price' => 300,
        'subtotal' => 300,
        'total' => 300,
        'status' => VisitServiceStatus::Completed,
    ]);

    // Patient paid 200 EGP (50% partial payment)
    Payment::factory()->create([
        'visit_id' => $visit->id,
        'shift_id' => $shift->id,
        'type' => 'payment',
        'amount' => 200,
        'payment_method' => PaymentMethod::Cash,
    ]);

    $reportService = app(App\Services\ServicesReportService::class);
    $data = $reportService->getReportData([$shift->id]);

    // Service 1 should receive 25% of 200 = 50 EGP
    expect($data['services'][$service1->id]['total_payments'])->toEqual(50.0);
    // Service 2 should receive 75% of 200 = 150 EGP
    expect($data['services'][$service2->id]['total_payments'])->toEqual(150.0);
    // Total payments should equal 200
    expect($data['overall']['total_payments'])->toEqual(200.0);
});
