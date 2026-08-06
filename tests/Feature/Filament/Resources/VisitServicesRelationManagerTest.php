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

it('can create multiple visit services with nested options', function () {
    $visit = PatientVisit::factory()->create();

    $service1 = Service::factory()->create(['base_price' => 200]);
    $group1 = ServiceOptionGroup::factory()->create([
        'service_id' => $service1->id,
        'selection_type' => SelectionType::Single,
    ]);
    $option1 = ServiceOption::factory()->create([
        'option_group_id' => $group1->id,
        'additional_price' => 50,
    ]);

    $service2 = Service::factory()->create(['base_price' => 300]);

    livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => EditPatientVisit::class,
    ])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'services' => [
                $service1->id => [
                    'selected' => true,
                    'quantity' => 1,
                    'unit_price' => 250,
                    'discount_value' => 10,
                    'total' => 240,
                    'status' => 'pending',
                    'options' => [
                        "group_{$group1->id}" => $option1->id,
                    ],
                ],
                $service2->id => [
                    'selected' => true,
                    'quantity' => 2,
                    'unit_price' => 300,
                    'discount_value' => 0,
                    'total' => 600,
                    'status' => 'pending',
                ],
            ],
        ])
        ->assertHasNoTableActionErrors();

    assertDatabaseHas(VisitService::class, [
        'visit_id' => $visit->id,
        'service_id' => $service1->id,
        'unit_price' => 250,
        'discount_value' => 10,
        'total' => 240,
    ]);

    assertDatabaseHas(VisitService::class, [
        'visit_id' => $visit->id,
        'service_id' => $service2->id,
        'quantity' => 2,
        'total' => 600,
    ]);

    $visitService1 = VisitService::where('visit_id', $visit->id)->where('service_id', $service1->id)->first();

    assertDatabaseHas(VisitServiceSelectedOption::class, [
        'visit_service_id' => $visitService1->id,
        'service_option_id' => $option1->id,
        'additional_price' => 50,
    ]);
});
