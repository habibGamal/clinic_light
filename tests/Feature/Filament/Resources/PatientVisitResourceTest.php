<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\PatientVisits\Pages\CreatePatientVisit;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\Pages\ListPatientVisits;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Shift;
use App\Models\User;

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
            'status' => VisitStatus::Waiting->value,
        ])
        ->call('create')
        ->assertNotified();

    \Pest\Laravel\assertDatabaseMissing(PatientVisit::class, [
        'patient_id' => $patient->id,
    ]);
});

it('can create a patient visit when a shift is open', function () {
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
            'status' => VisitStatus::Waiting->value,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(PatientVisit::class, [
        'patient_id' => $patient->id,
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting->value,
    ]);
});

it('can edit a patient visit', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);
    $visit = PatientVisit::factory()->create([
        'shift_id' => $shift->id,
        'status' => VisitStatus::Waiting,
    ]);

    livewire(EditPatientVisit::class, [
        'record' => $visit->id,
    ])
        ->fillForm([
            'status' => VisitStatus::Completed->value,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(PatientVisit::class, [
        'id' => $visit->id,
        'status' => VisitStatus::Completed->value,
    ]);
});
