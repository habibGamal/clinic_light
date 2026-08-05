<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceOption;
use App\Models\VisitService;
use App\Models\VisitServiceSelectedOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitServiceSelectedOption>
 */
final class VisitServiceSelectedOptionFactory extends Factory
{
    protected $model = VisitServiceSelectedOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_service_id' => VisitService::factory(),
            'service_option_id' => ServiceOption::factory(),
            'additional_price' => fake()->randomFloat(2, 0, 300),
        ];
    }
}
