<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use App\Models\VisitService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_service_id' => VisitService::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'report_text' => fake()->paragraphs(3, true),
        ];
    }
}
