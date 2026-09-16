<?php

declare(strict_types=1);

use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\VisitServiceStatus;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\VisitServicesRelationManager;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use App\Models\User;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use App\Services\InvoiceService;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates an invoice and maps services and options to invoice items', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 150.00]);

    $group = ServiceOptionGroup::create([
        'service_id' => $service->id,
        'name' => 'Extra Option Group',
        'selection_type' => 'single',
        'is_required' => false,
    ]);

    $option = ServiceOption::create([
        'option_group_id' => $group->id,
        'name' => 'Contrast Dye',
        'additional_price' => 50.00,
    ]);

    $vs = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 200.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 20.00,
        'subtotal' => 200.00,
        'total' => 180.00,
        'status' => VisitServiceStatus::Pending,
    ]);

    $optSelected = VisitServiceSelectedOption::create([
        'visit_service_id' => $vs->id,
        'service_option_id' => $option->id,
        'additional_price' => 50.00,
    ]);

    $invoice = app(InvoiceService::class)->syncInvoice($visit);

    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->total_amount)->toBe(180.0)
        ->and($invoice->items)->toHaveCount(2);

    $serviceItem = $invoice->items->firstWhere('type', 'service');
    $optionItem = $invoice->items->firstWhere('type', 'option');

    expect($serviceItem)->not->toBeNull()
        ->and($serviceItem->description)->toBe($service->name)
        ->and((float) $serviceItem->unit_price)->toBe(150.0)
        ->and((float) $serviceItem->total)->toBe(130.0)
        ->and($optionItem)->not->toBeNull()
        ->and((float) $optionItem->unit_price)->toBe(50.0)
        ->and((float) $optionItem->total)->toBe(50.0);
});

it('prevents editing completed visit services and hides edit action', function (): void {
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
        'status' => VisitServiceStatus::Completed,
    ]);

    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->assertTableActionHidden('edit', $vs)
        ->assertTableActionHidden('delete', $vs)
        ->assertTableActionVisible('refundAndCancel', $vs);
});

it('refunds and cancels a completed visit service and updates invoice items and balance', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 200.00]);

    $vs = VisitService::create([
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

    app(InvoiceService::class)->syncInvoice($visit);

    // Trigger refundAndCancel action
    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('refundAndCancel', $vs);

    $vs->refresh();
    expect($vs->status)->toBe(VisitServiceStatus::Cancelled);

    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->assertTableActionHidden('edit', $vs)
        ->assertTableActionHidden('delete', $vs)
        ->assertTableActionHidden('refundAndCancel', $vs);

    $invoice = $visit->refresh()->invoice->fresh(['items']);
    expect($invoice->items)->toHaveCount(2);

    $refundItem = $invoice->items->firstWhere('type', 'refund');
    expect($refundItem)->not->toBeNull()
        ->and((float) $refundItem->total)->toBe(-200.0)
        ->and((float) $invoice->total_amount)->toBe(0.0);
});

it('links payments to invoice and updates paid and remaining amounts', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
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

    app(InvoiceService::class)->syncInvoice($visit);

    livewire(PaymentsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('create', data: [
            'amount' => 200,
            'payment_method' => PaymentMethod::Cash,
        ])
        ->assertHasNoTableActionErrors();

    $invoice = $visit->refresh()->invoice->fresh(['payments']);
    expect((float) $invoice->paid_amount)->toBe(200.0)
        ->and((float) $invoice->remaining_amount)->toBe(100.0)
        ->and($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->payments)->toHaveCount(1);
});

it('allows recording a refund operation in payments section for cancelled visit services', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::factory()->create(['base_price' => 500.00]);

    $vs = VisitService::create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'quantity' => 1,
        'unit_price' => 500.00,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 0,
        'subtotal' => 500.00,
        'total' => 500.00,
        'status' => VisitServiceStatus::Completed,
    ]);

    app(InvoiceService::class)->syncInvoice($visit);

    // Initial payment of 500 EGP
    livewire(PaymentsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('create', data: [
            'amount' => 500,
            'payment_method' => PaymentMethod::Cash,
        ]);

    // Service gets cancelled & refunded
    app(InvoiceService::class)->refundAndCancelService($vs);

    // Register refund payment of 500 EGP in payments relation manager
    livewire(PaymentsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callTableAction('createRefund', data: [
            'amount' => 500,
            'payment_method' => PaymentMethod::Cash,
            'notes' => 'استرداد كلي للخدمة الملغاة',
        ])
        ->assertHasNoTableActionErrors();

    $invoice = $visit->refresh()->invoice->fresh(['payments']);
    expect((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) $invoice->remaining_amount)->toBe(0.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->payments)->toHaveCount(2);

    $refundPayment = $invoice->payments->firstWhere('type', 'refund');
    expect($refundPayment)->not->toBeNull()
        ->and((float) $refundPayment->amount)->toBe(-500.0);
});
