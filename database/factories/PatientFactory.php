<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
final class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'age' => fake()->numberBetween(5, 80),
            'gender' => fake()->randomElement(Gender::cases()),
            'address' => fake()->optional()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
