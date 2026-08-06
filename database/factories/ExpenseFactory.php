<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
final class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'created_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
