<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VisitServiceStatus;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\VisitService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitService>
 */
final class VisitServiceFactory extends Factory
{
    protected $model = VisitService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 100, 2000);
        $quantity = fake()->numberBetween(1, 3);
        $subtotal = $unitPrice * $quantity;
        $discount = fake()->randomFloat(2, 0, $subtotal * 0.2);

        return [
            'visit_id' => PatientVisit::factory(),
            'service_id' => Service::factory(),
            'technician_id' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_value' => $discount,
            'subtotal' => $subtotal,
            'total' => $subtotal - $discount,
            'status' => fake()->randomElement(VisitServiceStatus::cases()),
        ];
    }
}
