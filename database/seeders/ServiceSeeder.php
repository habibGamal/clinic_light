<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SelectionType;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOption;
use App\Models\ServiceOptionGroup;
use Illuminate\Database\Seeder;

final class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        ServiceOption::query()->delete();
        ServiceOptionGroup::query()->delete();
        Service::query()->delete();
        ServiceCategory::query()->delete();

        $categories = [
            [
                'name' => 'أشعة ثنائية الأبعاد',
                'code' => '2D',
                'services' => [
                    [
                        'name' => 'أشعة بانوراما (Panoramic X-ray - OPG)',
                        'code' => 'PAN',
                        'base_price' => 150,
                        'cost' => 30,
                        'option_groups' => [
                            [
                                'name' => 'نوع التقرير',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'عادي (Standard)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'تقرير بالذكاء الاصطناعي (Panorama Assisted AI Report)', 'additional_price' => 50, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'أشعة سيفالومتريك (Cephalometric X-ray)',
                        'code' => 'CEPH',
                        'base_price' => 200,
                        'cost' => 40,
                        'option_groups' => [
                            [
                                'name' => 'النوع والملفات',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'سيفالومتريك جانبي (Lateral Cephalometric)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'سيفالومتريك أمامي خلفي (PA)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'تحليل سيفالومتريك (Cephalometric Analysis)', 'additional_price' => 50, 'is_default' => false],
                                    ['name' => 'ملف تقويم كامل (Complete Orthodontic File)', 'additional_price' => 150, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'أشعة حول ذروية (Periapical)',
                        'code' => 'PA',
                        'base_price' => 80,
                        'cost' => 15,
                        'option_groups' => [
                            [
                                'name' => 'خيارات التصوير',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => false,
                                'options' => [
                                    ['name' => 'مع تحديد طول العمل (With Working Length)', 'additional_price' => 20, 'is_default' => false],
                                    ['name' => 'إزاحة إنسية (Mesial Shift)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'إزاحة وحشية (Distal Shift)', 'additional_price' => 0, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'أشعة العضة (Bitewing)',
                        'code' => 'BW',
                        'base_price' => 100,
                        'cost' => 20,
                        'option_groups' => [],
                    ],
                ],
            ],
            [
                'name' => 'أشعة ثلاثية الأبعاد',
                'code' => '3D',
                'services' => [
                    [
                        'name' => 'أشعة مقطعية ثلاثية الأبعاد (CBCT)',
                        'code' => 'CBCT',
                        'base_price' => 500,
                        'cost' => 100,
                        'option_groups' => [
                            [
                                'name' => '1. هدف الفحص (Aim of examination)',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'تحليل موقع الزراعة (Implant Site Analysis)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'آفة مرضية (Pathological Lesion)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'انحشار (Impaction)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'تقييم علاج الجذور (Endodontic Evaluation)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'تصدع (Crack)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'كسر (Fracture)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'صدمة / رضوض (Trauma)', 'additional_price' => 0, 'is_default' => false],
                                ],
                            ],
                            [
                                'name' => '2. منطقة الاهتمام / مجال الرؤية (Region of interest)',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'منطقة محدودة - الربع العلوي (يسار)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'منطقة محدودة - الربع العلوي (أمامي)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'منطقة محدودة - الربع العلوي (يمين)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'منطقة محدودة - الربع السفلي (يسار)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'منطقة محدودة - الربع السفلي (أمامي)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'منطقة محدودة - الربع السفلي (يمين)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'فك كامل - علوي (Full Arch Upper)', 'additional_price' => 150, 'is_default' => false],
                                    ['name' => 'فك كامل - سفلي (Full Arch Lower)', 'additional_price' => 150, 'is_default' => false],
                                    ['name' => 'فك كامل - الفكين (Full Arch Both)', 'additional_price' => 300, 'is_default' => true],
                                ],
                            ],
                            [
                                'name' => '3. فحوصات متقدمة (Advanced)',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => false,
                                'options' => [
                                    ['name' => 'وضع علاج الجذور (Endo Mode)', 'additional_price' => 100, 'is_default' => false],
                                    ['name' => 'تصوير الجمجمة والوجه (Full Skull - 3D Face Imaging)', 'additional_price' => 300, 'is_default' => false],
                                    ['name' => 'المفصل الصدغي الفكي (TMJ Open/Closed)', 'additional_price' => 200, 'is_default' => false],
                                    ['name' => 'مسح طقم الأسنان (Denture Scan)', 'additional_price' => 150, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'الخدمات الرقمية والمسح',
                'code' => 'DIG',
                'services' => [
                    [
                        'name' => 'مسح داخل الفم (Intra-Oral Scan)',
                        'code' => 'IOS',
                        'base_price' => 800,
                        'cost' => 150,
                        'option_groups' => [
                            [
                                'name' => 'نوع التركيبة / الفحص',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'تيجان وجسور (Crown & Bridges)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'تركيبات زراعة (Implant Prosthetics)', 'additional_price' => 200, 'is_default' => false],
                                    ['name' => 'قياس ضوئي داخل الفم (Intraoral Photogrammetry - IPG)', 'additional_price' => 300, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'مسح خارج الفم (Extra-Oral Scan)',
                        'code' => 'EOS',
                        'base_price' => 400,
                        'cost' => 80,
                        'option_groups' => [
                            [
                                'name' => 'نوع المسح',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'مسح نموذج جصي (Stone Model Scan)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'مسح طبعة (Impression)', 'additional_price' => 50, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'مسح الوجه ثلاثي الأبعاد (3D Facial Scan)',
                        'code' => 'DFS',
                        'base_price' => 600,
                        'cost' => 100,
                        'option_groups' => [],
                    ],
                    [
                        'name' => 'دليل جراحي للزراعة (Implant Surgical Guide)',
                        'code' => 'ISG',
                        'base_price' => 1200,
                        'cost' => 250,
                        'option_groups' => [
                            [
                                'name' => 'نوع طقم الزراعة',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'طقم زراعة عام (Universal implant Kit)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'طقم زراعة مخصص (Specific implant kit)', 'additional_price' => 200, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'دليل قص اللثة (Gingivectomy Guide)',
                        'code' => 'GG',
                        'base_price' => 500,
                        'cost' => 100,
                        'option_groups' => [],
                    ],
                    [
                        'name' => 'تصميم الابتسامة الرقمي (Digital Smile Design - DSD)',
                        'code' => 'DSD',
                        'base_price' => 1500,
                        'cost' => 300,
                        'option_groups' => [
                            [
                                'name' => 'صيغة النموذج (Mock-up Format)',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'نموذج بصيغة STL (Mock-up STL format)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'نموذج مع دليل شفاف (Mock-up With Transparent Index)', 'additional_price' => 200, 'is_default' => false],
                                ],
                            ],
                            [
                                'name' => 'منطقة الاهتمام (Area of Interest)',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'فك علوي (Upper)', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'فك سفلي (Lower)', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'الفكين (Both)', 'additional_price' => 300, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = ServiceCategory::query()->create([
                'name' => $categoryData['name'],
                'code' => $categoryData['code'],
            ]);

            foreach ($categoryData['services'] as $serviceData) {
                $service = Service::query()->create([
                    'category_id' => $category->id,
                    'name' => $serviceData['name'],
                    'code' => $serviceData['code'],
                    'base_price' => $serviceData['base_price'],
                    'cost' => $serviceData['cost'],
                    'is_active' => true,
                ]);

                foreach ($serviceData['option_groups'] as $sortOrder => $groupData) {
                    $group = ServiceOptionGroup::query()->create([
                        'service_id' => $service->id,
                        'name' => $groupData['name'],
                        'selection_type' => $groupData['selection_type'],
                        'is_required' => $groupData['is_required'],
                        'sort_order' => $sortOrder,
                    ]);

                    foreach ($groupData['options'] as $optionSort => $optionData) {
                        ServiceOption::query()->create([
                            'option_group_id' => $group->id,
                            'name' => $optionData['name'],
                            'additional_price' => $optionData['additional_price'],
                            'is_default' => $optionData['is_default'],
                            'sort_order' => $optionSort,
                        ]);
                    }
                }
            }
        }
    }
}
