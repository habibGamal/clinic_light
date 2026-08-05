<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
final class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity_type' => fake()->randomElement(['App\\Models\\Patient', 'App\\Models\\PatientVisit']),
            'entity_id' => fake()->numberBetween(1, 100),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => ['name' => fake()->name()],
            'created_at' => now(),
        ];
    }
}
