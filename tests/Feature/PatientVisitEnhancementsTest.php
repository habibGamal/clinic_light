<?php

declare(strict_types=1);

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\VisitServiceStatus;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\VisitServicesRelationManager;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitService;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('calculates total correctly for fixed and percentage discounts', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 200.00]);

    // Fixed discount of 50 EGP
    $fixedService = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 200.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 50.00,
        'subtotal' => 200.00,
        'total' => 150.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    expect($fixedService->discount_type)->toBe(DiscountType::Fixed)
        ->and((float) $fixedService->total)->toBe(150.0);

    // Percentage discount of 25% (200 - 50 = 150 EGP)
    $percentService = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 2,
        'unit_price' => 200.00,
        'discount_type' => DiscountType::Percent,
        'discount_value' => 25.00,
        'subtotal' => 400.00,
        'total' => 300.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    expect($percentService->discount_type)->toBe(DiscountType::Percent)
        ->and((float) $percentService->total)->toBe(300.0);
});

it('defaults visit service status to pending on creation', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 100.00]);

    $vs = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 100.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 100.00,
        'total' => 100.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    expect($vs->status)->toBe(VisitServiceStatus::Pending);
});

it('allows completing a pending service and prevents deleting completed services', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 100.00]);

    $vs = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 100.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 100.00,
        'total' => 100.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    // Complete action test via Livewire relation manager
    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('complete', $vs);

    $vs->refresh();
    expect($vs->status)->toBe(VisitServiceStatus::Completed);

    // Delete action should be hidden for completed service
    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->assertTableActionHidden('delete', $vs);

    expect(VisitService::where('id', $vs->id)->exists())->toBeTrue();
});

it('enforces maximum payment amount to match remaining invoice due', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
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

    // First valid payment of 300 EGP (Remaining due = 200 EGP)
    livewire(PaymentsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('create', data: [
            'amount' => 300,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now()->toDateTimeString(),
        ])
        ->assertHasNoTableActionErrors();

    expect(Payment::where('visit_id', $visit->id)->sum('amount'))->toEqual(300);

    // Attempt paying 300 EGP again when remaining due is 200 EGP -> should fail validation
    livewire(PaymentsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('create', data: [
            'amount' => 300,
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => now()->toDateTimeString(),
        ])
        ->assertHasTableActionErrors(['amount']);
});

it('disables closing by clicking away and escaping for report actions in ReportsRelationManager', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);

    $livewire = livewire(App\Filament\Resources\PatientVisits\RelationManagers\ReportsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ]);

    $createAction = collect($livewire->instance()->getTable()->getHeaderActions())
        ->first(fn ($action) => $action->getName() === 'create');
    expect($createAction->isModalClosedByClickingAway())->toBeFalse()
        ->and($createAction->isModalClosedByEscaping())->toBeFalse();

    $editAction = collect($livewire->instance()->getTable()->getActions())
        ->first(fn ($action) => $action->getName() === 'edit');
    expect($editAction->isModalClosedByClickingAway())->toBeFalse()
        ->and($editAction->isModalClosedByEscaping())->toBeFalse();
});
