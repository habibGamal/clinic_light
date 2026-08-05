<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\ReferringDoctor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ServiceSeeder::class,
        ]);

        $doctorRole = Role::query()->where('name', 'doctor')->first();

        // Create default admin user as a doctor
        User::factory()->create([
            'role_id' => $doctorRole->id,
            'name' => config('app.default_user.name'),
            'email' => config('app.default_user.email'),
            'password' => bcrypt(config('app.default_user.password')),
            'specialization' => 'أشعة',
            'is_active' => true,
        ]);

        // Create sample users
        User::factory(2)->doctor()->create();
        User::factory(2)->technician()->create();

        // Create sample referring doctors
        ReferringDoctor::factory(5)->create();

        // Create sample patients
        Patient::factory(10)->create();
    }
}
