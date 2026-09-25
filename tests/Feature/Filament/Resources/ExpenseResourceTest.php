<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shift;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render expense list page in filament for viewing only', function () {
    livewire(ListExpenses::class)
        ->assertOk();
});

it('disallows all CRUD operations in filament for expenses', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);
    $expense = Expense::factory()->create([
        'shift_id' => $shift->id,
        'amount' => 100.00,
    ]);

    expect(ExpenseResource::canCreate())->toBeFalse()
        ->and(ExpenseResource::canEdit($expense))->toBeFalse()
        ->and(ExpenseResource::canDelete($expense))->toBeFalse()
        ->and(ExpenseResource::canDeleteAny())->toBeFalse();
});

it('has many expenses relationship on expense category', function () {
    $category = ExpenseCategory::factory()->create();
    $expense1 = Expense::factory()->create(['expense_category_id' => $category->id]);
    $expense2 = Expense::factory()->create(['expense_category_id' => $category->id]);

    expect($category->expenses)->toHaveCount(2)
        ->and($category->expenses->pluck('id')->toArray())->toContain($expense1->id, $expense2->id);
});

it('allows editing an expense only within the same active open shift', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);
    $expense = Expense::factory()->create([
        'shift_id' => $shift->id,
        'amount' => 100.00,
    ]);

    expect($expense->isEditable($shift))->toBeTrue();

    // Should succeed within the same shift
    $expense->update(['amount' => 200.00]);
    expect($expense->fresh()->amount)->toBe('200.00');
});

it('throws domain exception when attempting to update or delete closed shift expense via model', function () {
    $shift = Shift::factory()->closed()->create();
    $expense = Expense::factory()->create([
        'shift_id' => $shift->id,
        'amount' => 100.00,
    ]);

    expect($expense->isEditable(null))->toBeFalse();

    expect(fn () => $expense->update(['amount' => 999.00]))
        ->toThrow(DomainException::class);

    expect(fn () => $expense->delete())
        ->toThrow(DomainException::class);
});
