<?php

declare(strict_types=1);

use App\Enums\ShiftStatus;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shift;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render expense list page', function () {
    livewire(ListExpenses::class)
        ->assertOk();
});

it('can render expense create page', function () {
    livewire(CreateExpense::class)
        ->assertOk();
});

it('can create an expense with category and sets created_by to logged in user', function () {
    $category = ExpenseCategory::factory()->create();

    livewire(CreateExpense::class)
        ->fillForm([
            'expense_category_id' => $category->id,
            'amount' => 1500.00,
            'notes' => 'ملاحظة مصروف',
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Expense::class, [
        'expense_category_id' => $category->id,
        'amount' => 1500.00,
        'created_by' => $this->user->id,
        'notes' => 'ملاحظة مصروف',
    ]);
});

it('assigns open shift to expense if shift is currently open', function () {
    $shift = Shift::factory()->create([
        'user_id' => $this->user->id,
        'status' => ShiftStatus::Open,
    ]);
    $category = ExpenseCategory::factory()->create();

    livewire(CreateExpense::class)
        ->fillForm([
            'expense_category_id' => $category->id,
            'amount' => 500.00,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Expense::class, [
        'expense_category_id' => $category->id,
        'shift_id' => $shift->id,
        'amount' => 500.00,
    ]);
});

it('allows expense creation when no shift is open', function () {
    $category = ExpenseCategory::factory()->create();

    livewire(CreateExpense::class)
        ->fillForm([
            'expense_category_id' => $category->id,
            'amount' => 300.00,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Expense::class, [
        'expense_category_id' => $category->id,
        'shift_id' => null,
        'amount' => 300.00,
    ]);
});

it('has many expenses relationship on expense category', function () {
    $category = ExpenseCategory::factory()->create();
    $expense1 = Expense::factory()->create(['expense_category_id' => $category->id]);
    $expense2 = Expense::factory()->create(['expense_category_id' => $category->id]);

    expect($category->expenses)->toHaveCount(2)
        ->and($category->expenses->pluck('id')->toArray())->toContain($expense1->id, $expense2->id);
});
