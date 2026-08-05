<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->upsert([
            ['name' => 'doctor', 'description' => 'طبيب - يمكنه إنشاء التقارير وإدارة المرضى والزيارات'],
            ['name' => 'technician', 'description' => 'فني - يمكنه إجراء الفحوصات وإدارة الورديات والمدفوعات'],
        ], ['name'], ['description']);
    }
}
