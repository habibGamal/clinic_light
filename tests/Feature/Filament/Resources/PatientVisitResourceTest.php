<?php

declare(strict_types=1);

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\VisitServiceStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\Pages\CreatePatientVisit;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\Pages\ListPatientVisits;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Shift;
use App\Models\User;
use App\Models\VisitService;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render patient visit list page', function () {
    livewire(ListPatientVisits::class)
        ->assertOk();
});

it('can render patient visit create page', function () {
    livewire(CreatePatientVisit::class)
        ->assertOk();
});

it('cannot create a patient visit if no shift is open', function () {
    $patient = Patient::factory()->create();

    livewire(CreatePatientVisit::class)
        ->fillForm([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertNotified();

    \Pest\Laravel\assertDatabaseMissing(PatientVisit::class, [
        'patient_id' => $patient->id,
    ]);
});

it('can create a patient visit when a shift is open and defaults to waiting status', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
        'opened_at' => now(),
    ]);

    $patient = Patient::factory()->create();

    livewire(CreatePatientVisit::class)
        ->fillForm([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateTimeString(),
            'notes' => 'New visit notes',
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(PatientVisit::class, [
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting->value,
        'notes' => 'New visit notes',
    ]);
});

it('can edit a patient visit when status is waiting', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);
    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    expect(PatientVisitResource::canEdit($visit))->toBeTrue();

    livewire(EditPatientVisit::class, [
        'record' => $visit->id,
    ])
        ->fillForm([
            'notes' => 'Updated notes',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'notes' => 'Updated notes',
    ]);
});

it('cannot edit a patient visit when status is completed or cancelled', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $completedVisit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Completed,
    ]);

    $cancelledVisit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Cancelled,
    ]);

    expect(PatientVisitResource::canEdit($completedVisit))->toBeFalse()
        ->and(PatientVisitResource::canEdit($cancelledVisit))->toBeFalse();

    livewire(EditPatientVisit::class, [
        'record' => $completedVisit->id,
    ])->assertForbidden();

    livewire(EditPatientVisit::class, [
        'record' => $cancelledVisit->id,
    ])->assertForbidden();
});

it('can complete or cancel a visit via table actions', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $visit1 = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $visit2 = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    livewire(ListPatientVisits::class)
        ->callAction(TestAction::make('complete')->table($visit1))
        ->assertNotified();

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit1->id,
        'status' => VisitStatus::Completed->value,
    ]);

    livewire(ListPatientVisits::class)
        ->callAction(TestAction::make('cancel')->table($visit2))
        ->assertNotified();

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit2->id,
        'status' => VisitStatus::Cancelled->value,
    ]);
});

it('cannot complete a visit via table action if there are due payments', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $service = Service::factory()->create(['base_price' => 500.00]);
    VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 500.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 500.00,
        'total' => 500.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    app(App\Services\InvoiceService::class)->syncInvoice($visit);

    livewire(ListPatientVisits::class)
        ->callAction(TestAction::make('complete')->table($visit))
        ->assertNotified('لا يمكن إكمال الزيارة');

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'status' => VisitStatus::Waiting->value,
    ]);
});

it('cannot complete a visit via edit page header action if there are due payments', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $service = Service::factory()->create(['base_price' => 300.00]);
    VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 300.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 300.00,
        'total' => 300.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    app(App\Services\InvoiceService::class)->syncInvoice($visit);

    livewire(EditPatientVisit::class, ['record' => $visit->id])
        ->callAction('complete')
        ->assertNotified('لا يمكن إكمال الزيارة');

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'status' => VisitStatus::Waiting->value,
    ]);
});

it('can complete a visit when remaining balance is fully paid', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    $service = Service::factory()->create(['base_price' => 200.00]);
    VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 200.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 200.00,
        'total' => 200.00,
        'status' => VisitServiceStatus::Completed,
    ]);

    Payment::create([
        'visit_id' => $visit->id,
        'amount' => 200.00,
        'payment_method' => PaymentMethod::Cash,
    ]);

    app(App\Services\InvoiceService::class)->syncInvoice($visit);

    expect($visit->hasDuePayments())->toBeFalse();

    livewire(ListPatientVisits::class)
        ->callAction(TestAction::make('complete')->table($visit))
        ->assertNotified();

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'status' => VisitStatus::Completed->value,
    ]);
});

it('relates payments to the active shift', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);

    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
    ]);

    $payment = Payment::create([
        'visit_id' => $visit->id,
        'amount' => 150.00,
        'payment_method' => PaymentMethod::Cash,
    ]);

    expect($payment->shift_id)->toBe($shift->id)
        ->and($payment->shift->id)->toBe($shift->id)
        ->and($shift->payments->pluck('id'))->toContain($payment->id);
});

it('displays patient phone in visit form select option and searches by phone', function () {
    $patient = Patient::factory()->create([
        'full_name' => 'محمد أحمد',
        'phone' => '01011113333',
    ]);

    $livewire = livewire(CreatePatientVisit::class);

    /** @var Filament\Forms\Components\Select $patientSelect */
    $patientSelect = $livewire->instance()->getSchemaComponent('form.patient_id');

    expect($patientSelect)->not->toBeNull()
        ->and($patientSelect->getSearchColumns())->toBe(['full_name', 'phone']);

    $optionLabel = $patientSelect->getOptionLabelFromRecord($patient);
    expect($optionLabel)->toContain('محمد أحمد')
        ->and($optionLabel)->toContain('01011113333');
});

it('rejects duplicate phone when quick creating a patient in visit form', function () {
    Patient::factory()->create([
        'phone' => '01011113333',
    ]);

    livewire(CreatePatientVisit::class)
        ->callFormComponentAction('patient_id', 'createOption', data: [
            'full_name' => 'مريض برقم مكرر',
            'phone' => '01011113333',
        ])
        ->assertHasFormComponentActionErrors(['phone' => 'unique']);
});
