<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTemplate>
 */
final class ReportTemplateFactory extends Factory
{
    protected $model = ReportTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'service_id' => null,
            'content' => '<p><strong>تقرير: {service_name}</strong></p><p>المشاهدات: [ ... ]</p><p>التشخيص: [ ... ]</p>',
            'is_active' => true,
            'user_id' => User::factory(),
        ];
    }
}
