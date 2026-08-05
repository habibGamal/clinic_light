<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use App\Models\VisitService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
final class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/dicom'];

        return [
            'visit_service_id' => VisitService::factory(),
            'file_name' => fake()->word().'.'.fake()->randomElement(['jpg', 'png', 'pdf', 'dcm']),
            'file_path' => 'attachments/'.fake()->uuid().'.jpg',
            'mime_type' => fake()->randomElement($mimeTypes),
            'file_size' => fake()->numberBetween(10000, 50000000),
            'uploaded_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
