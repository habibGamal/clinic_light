<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
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
            'title' => fake()->sentence(3),
            'category' => fake()->randomElement(['إيجار', 'كهرباء', 'مرتبات', 'مستلزمات', 'صيانة']),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'expense_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'created_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
