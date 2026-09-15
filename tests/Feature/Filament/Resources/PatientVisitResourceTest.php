<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\Pages\CreatePatientVisit;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\Pages\ListPatientVisits;
use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Shift;
use App\Models\User;
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
