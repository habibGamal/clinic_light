<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SelectionType;
use App\Models\Service;
use App\Models\ServiceOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOptionGroup>
 */
final class ServiceOptionGroupFactory extends Factory
{
    protected $model = ServiceOptionGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'name' => fake()->randomElement(['الغرض', 'المنطقة', 'إضافات']),
            'selection_type' => fake()->randomElement(SelectionType::cases()),
            'is_required' => fake()->boolean(70),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
