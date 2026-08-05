<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOption>
 */
final class ServiceOptionFactory extends Factory
{
    protected $model = ServiceOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'option_group_id' => ServiceOptionGroup::factory(),
            'name' => fake()->word(),
            'additional_price' => fake()->randomFloat(2, 0, 500),
            'is_default' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
