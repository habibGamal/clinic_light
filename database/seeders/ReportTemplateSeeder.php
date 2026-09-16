<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ReportTemplate;
use App\Services\MedicalReportTemplateService;
use Illuminate\Database\Seeder;

final class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'قالب فحص الأشعة السينية (2D / OPG / X-Ray)',
                'content' => MedicalReportTemplateService::generateTemplate(null, MedicalReportTemplateService::TYPE_RADIOLOGY),
                'is_active' => true,
            ],
            [
                'name' => 'قالب الأشعة المقطعية المخروطية (3D CBCT)',
                'content' => MedicalReportTemplateService::generateTemplate(null, MedicalReportTemplateService::TYPE_CBCT),
                'is_active' => true,
            ],
            [
                'name' => 'قالب المسح والتصميم الرقمي والأدلة الجراحية (Digital / Guides)',
                'content' => MedicalReportTemplateService::generateTemplate(null, MedicalReportTemplateService::TYPE_DIGITAL_SCAN),
                'is_active' => true,
            ],
            [
                'name' => 'قالب تقرير طبي وسريري عام (General Clinical)',
                'content' => MedicalReportTemplateService::generateTemplate(null, MedicalReportTemplateService::TYPE_GENERAL),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            ReportTemplate::query()->firstOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
