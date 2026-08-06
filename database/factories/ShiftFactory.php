<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
final class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'opening_balance' => fake()->randomFloat(2, 0, 5000),
            'closing_balance' => null,
            'actual_cash' => null,
            'difference' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'status' => ShiftStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'closing_balance' => fake()->randomFloat(2, 1000, 15000),
            'actual_cash' => fake()->randomFloat(2, 1000, 15000),
            'difference' => fake()->randomFloat(2, -100, 100),
            'closed_at' => now(),
            'status' => ShiftStatus::Closed,
        ]);
    }
}
