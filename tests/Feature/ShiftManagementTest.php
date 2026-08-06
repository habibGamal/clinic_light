<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Livewire\ShiftToggle;
use App\Models\Shift;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

test('shift resource disables create edit and delete actions', function (): void {
    expect(ShiftResource::canCreate())->toBeFalse();
    expect(ShiftResource::canEdit(Shift::factory()->make()))->toBeFalse();
    expect(ShiftResource::canDelete(Shift::factory()->make()))->toBeFalse();
    expect(ShiftResource::canDeleteAny())->toBeFalse();
});

test('user can open a shift via shift toggle component', function (): void {
    $this->actingAs($this->user);

    livewire(ShiftToggle::class)
        ->callAction('openShift', [
            'opening_balance' => 250.00,
        ])
        ->assertNotified();

    assertDatabaseHas(Shift::class, [
        'user_id' => $this->user->id,
        'opening_balance' => 250.00,
        'status' => ShiftStatus::Open->value,
    ]);

    $shift = Shift::query()->where('user_id', $this->user->id)->first();
    expect($shift->opened_at)->not->toBeNull();
});

test('user can close an active shift via shift toggle component', function (): void {
    $this->actingAs($this->user);

    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'opening_balance' => 300.00,
        'status' => ShiftStatus::Open,
        'opened_at' => now()->subHours(4),
    ]);

    livewire(ShiftToggle::class)
        ->callAction('closeShift', [
            'closing_balance' => 1200.00,
        ])
        ->assertNotified();

    $shift->refresh();

    expect($shift->status)->toBe(ShiftStatus::Closed);
    expect((float) $shift->closing_balance)->toBe(1200.00);
    expect((float) $shift->actual_cash)->toBe(1200.00);
    expect((float) $shift->difference)->toBe(0.00);
    expect($shift->closed_at)->not->toBeNull();
});
