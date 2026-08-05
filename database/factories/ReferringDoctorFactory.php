<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReferringDoctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferringDoctor>
 */
final class ReferringDoctorFactory extends Factory
{
    protected $model = ReferringDoctor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'د. '.fake()->name(),
            'phone' => fake()->phoneNumber(),
            'specialization' => fake()->randomElement(['أسنان عام', 'تقويم', 'جراحة وجه وفكين', 'تركيبات', 'علاج جذور']),
            'address' => fake()->address(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
