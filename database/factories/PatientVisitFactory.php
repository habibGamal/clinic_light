<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\ReferringDoctor;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientVisit>
 */
final class PatientVisitFactory extends Factory
{
    protected $model = PatientVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'referring_doctor_id' => fake()->optional(0.5)->passthrough(ReferringDoctor::query()->inRandomOrder()->value('id')),
            'shift_id' => Shift::query()->inRandomOrder()->value('id'),
            'visit_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => fake()->randomElement(VisitStatus::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
