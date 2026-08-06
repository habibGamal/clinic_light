<?php

declare(strict_types=1);

use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('can render service list page', function () {
    livewire(ListServices::class)->assertOk();
});

it('can render service edit page', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'category_id' => $category->id,
    ]);

    livewire(EditService::class, [
        'record' => $service->getKey(),
    ])->assertOk();
});

it('can access serviceCategory relationship on Service model', function () {
    $category = ServiceCategory::factory()->create();
    $service = Service::factory()->create([
        'category_id' => $category->id,
    ]);

    expect($service->serviceCategory)->not->toBeNull()
        ->and($service->serviceCategory->id)->toBe($category->id);
});
