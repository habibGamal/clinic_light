<?php

declare(strict_types=1);

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\Patient;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

it('can render patient list page', function () {
    livewire(ListPatients::class)
        ->assertOk();
});

it('can render patient create page', function () {
    livewire(CreatePatient::class)
        ->assertOk();
});

it('can create a patient', function () {
    $patientData = Patient::factory()->make();

    livewire(CreatePatient::class)
        ->fillForm([
            'full_name' => $patientData->full_name,
            'phone' => $patientData->phone,
            'age' => $patientData->age,
            'gender' => $patientData->gender,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Patient::class, [
        'full_name' => $patientData->full_name,
    ]);
});

it('can edit a patient', function () {
    $patient = Patient::factory()->create();

    livewire(EditPatient::class, [
        'record' => $patient->id,
    ])
        ->fillForm([
            'full_name' => 'مريض معدل',
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Patient::class, [
        'id' => $patient->id,
        'full_name' => 'مريض معدل',
    ]);
});
