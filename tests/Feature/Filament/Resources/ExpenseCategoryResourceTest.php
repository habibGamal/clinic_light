<?php

declare(strict_types=1);

use App\Filament\Resources\ExpenseCategories\Pages\CreateExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\EditExpenseCategory;
use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseCategories\RelationManagers\ExpensesRelationManager;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Filament\Actions\DeleteAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render expense category list page', function () {
    $categories = ExpenseCategory::factory()->count(3)->create();

    livewire(ListExpenseCategories::class)
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords($categories);
});

it('can render expense category create page', function () {
    livewire(CreateExpenseCategory::class)
        ->assertOk();
});

it('can create an expense category', function () {
    livewire(CreateExpenseCategory::class)
        ->fillForm([
            'name' => 'نثريات ومطبوعات',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(ExpenseCategory::class, [
        'name' => 'نثريات ومطبوعات',
    ]);
});

it('validates required and unique name', function () {
    $existing = ExpenseCategory::factory()->create(['name' => 'إيجار']);

    livewire(CreateExpenseCategory::class)
        ->fillForm([
            'name' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);

    livewire(CreateExpenseCategory::class)
        ->fillForm([
            'name' => 'إيجار',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('can render expense category edit page', function () {
    $category = ExpenseCategory::factory()->create();

    livewire(EditExpenseCategory::class, [
        'record' => $category->getKey(),
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $category->name,
        ]);
});

it('can update an expense category', function () {
    $category = ExpenseCategory::factory()->create([
        'name' => 'اسم قديم',
    ]);

    livewire(EditExpenseCategory::class, [
        'record' => $category->getKey(),
    ])
        ->fillForm([
            'name' => 'اسم جديد',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    assertDatabaseHas(ExpenseCategory::class, [
        'id' => $category->id,
        'name' => 'اسم جديد',
    ]);
});

it('can delete an expense category', function () {
    $category = ExpenseCategory::factory()->create();

    livewire(EditExpenseCategory::class, [
        'record' => $category->getKey(),
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    assertDatabaseMissing(ExpenseCategory::class, [
        'id' => $category->id,
    ]);
});

it('can render expenses relation manager on edit page', function () {
    $category = ExpenseCategory::factory()->create();
    $expense = Expense::factory()->create([
        'expense_category_id' => $category->id,
    ]);

    livewire(ExpensesRelationManager::class, [
        'ownerRecord' => $category,
        'pageClass' => EditExpenseCategory::class,
    ])
        ->loadTable()
        ->assertOk()
        ->assertCanSeeTableRecords([$expense]);
});
