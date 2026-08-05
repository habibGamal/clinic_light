<?php

declare(strict_types=1);

use App\Enums\SelectionType;
use App\Filament\Resources\PatientVisits\Pages\EditPatientVisit;
use App\Filament\Resources\PatientVisits\RelationManagers\VisitServicesRelationManager;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use App\Models\User;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('can render visit services relation manager', function () {
    $visit = PatientVisit::factory()->create();

    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])->assertOk();
});

it('can create a visit service with option groups without column error', function () {
    $visit = PatientVisit::factory()->create();
    $service = Service::factory()->create(['base_price' => 200]);

    $group = ServiceOptionGroup::factory()->create([
        'service_id' => $service->id,
        'selection_type' => SelectionType::Single,
    ]);

    $option = ServiceOption::factory()->create([
        'option_group_id' => $group->id,
        'additional_price' => 50,
    ]);

    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 250,
            'discount_value' => 0,
            'total' => 250,
            'status' => 'pending',
            "selected_options_group_{$group->id}" => $option->id,
        ])
        ->assertHasNoTableActionErrors();

    assertDatabaseHas(VisitService::class, [
        'visit_id' => $visit->id,
        'service_id' => $service->id,
        'total' => 250,
    ]);

    $visitService = VisitService::where('visit_id', $visit->id)->first();

    assertDatabaseHas(VisitServiceSelectedOption::class, [
        'visit_service_id' => $visitService->id,
        'service_option_id' => $option->id,
        'additional_price' => 50,
    ]);
});
