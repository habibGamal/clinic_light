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
                'name' => '2D Radiography',
                'code' => '2D',
                'services' => [
                    [
                        'name' => 'Panoramic X-ray (OPG)',
                        'code' => 'PAN',
                        'base_price' => 150,
                        'cost' => 30,
                        'option_groups' => [
                            [
                                'name' => 'Report Type',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Standard', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Panorama Assisted AI Report', 'additional_price' => 50, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Cephalometric X-ray',
                        'code' => 'CEPH',
                        'base_price' => 200,
                        'cost' => 40,
                        'option_groups' => [
                            [
                                'name' => 'Type and Files',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Lateral Cephalometric', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Posterior-Anterior (PA) Cephalometric', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Cephalometric Analysis', 'additional_price' => 50, 'is_default' => false],
                                    ['name' => 'Complete Orthodontic File', 'additional_price' => 150, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Periapical X-ray',
                        'code' => 'PA',
                        'base_price' => 80,
                        'cost' => 15,
                        'option_groups' => [
                            [
                                'name' => 'Imaging Options',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => false,
                                'options' => [
                                    ['name' => 'With Working Length', 'additional_price' => 20, 'is_default' => false],
                                    ['name' => 'Mesial Shift', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Distal Shift', 'additional_price' => 0, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Bitewing X-ray',
                        'code' => 'BW',
                        'base_price' => 100,
                        'cost' => 20,
                        'option_groups' => [],
                    ],
                ],
            ],
            [
                'name' => '3D Radiography',
                'code' => '3D',
                'services' => [
                    [
                        'name' => '3D Cone Beam CT (CBCT)',
                        'code' => 'CBCT',
                        'base_price' => 500,
                        'cost' => 100,
                        'option_groups' => [
                            [
                                'name' => '1. Aim of Examination',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Implant Site Analysis', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Pathological Lesion', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Impaction', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Endodontic Evaluation', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Crack', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Fracture', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Trauma', 'additional_price' => 0, 'is_default' => false],
                                ],
                            ],
                            [
                                'name' => '2. Region of Interest',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Limited Region - Upper Left Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Limited Region - Upper Anterior Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Limited Region - Upper Right Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Limited Region - Lower Left Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Limited Region - Lower Anterior Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Limited Region - Lower Right Quadrant', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Full Arch Upper', 'additional_price' => 150, 'is_default' => false],
                                    ['name' => 'Full Arch Lower', 'additional_price' => 150, 'is_default' => false],
                                    ['name' => 'Full Arch Both', 'additional_price' => 300, 'is_default' => true],
                                ],
                            ],
                            [
                                'name' => '3. Advanced Examinations',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => false,
                                'options' => [
                                    ['name' => 'Endo Mode', 'additional_price' => 100, 'is_default' => false],
                                    ['name' => 'Full Skull - 3D Face Imaging', 'additional_price' => 300, 'is_default' => false],
                                    ['name' => 'TMJ Open/Closed', 'additional_price' => 200, 'is_default' => false],
                                    ['name' => 'Denture Scan', 'additional_price' => 150, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Digital Services & Scanning',
                'code' => 'DIG',
                'services' => [
                    [
                        'name' => 'Intra-Oral Scan',
                        'code' => 'IOS',
                        'base_price' => 800,
                        'cost' => 150,
                        'option_groups' => [
                            [
                                'name' => 'Restoration / Examination Type',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Crown & Bridges', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Implant Prosthetics', 'additional_price' => 200, 'is_default' => false],
                                    ['name' => 'Intraoral Photogrammetry (IPG)', 'additional_price' => 300, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Extra-Oral Scan',
                        'code' => 'EOS',
                        'base_price' => 400,
                        'cost' => 80,
                        'option_groups' => [
                            [
                                'name' => 'Scan Type',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Stone Model Scan', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Impression Scan', 'additional_price' => 50, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => '3D Facial Scan',
                        'code' => 'DFS',
                        'base_price' => 600,
                        'cost' => 100,
                        'option_groups' => [],
                    ],
                    [
                        'name' => 'Implant Surgical Guide',
                        'code' => 'ISG',
                        'base_price' => 1200,
                        'cost' => 250,
                        'option_groups' => [
                            [
                                'name' => 'Implant Kit Type',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Universal Implant Kit', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Specific Implant Kit', 'additional_price' => 200, 'is_default' => false],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Gingivectomy Guide',
                        'code' => 'GG',
                        'base_price' => 500,
                        'cost' => 100,
                        'option_groups' => [],
                    ],
                    [
                        'name' => 'Digital Smile Design (DSD)',
                        'code' => 'DSD',
                        'base_price' => 1500,
                        'cost' => 300,
                        'option_groups' => [
                            [
                                'name' => 'Mock-up Format',
                                'selection_type' => SelectionType::Multi,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Mock-up STL Format', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Mock-up With Transparent Index', 'additional_price' => 200, 'is_default' => false],
                                ],
                            ],
                            [
                                'name' => 'Area of Interest',
                                'selection_type' => SelectionType::Single,
                                'is_required' => true,
                                'options' => [
                                    ['name' => 'Upper Arch', 'additional_price' => 0, 'is_default' => true],
                                    ['name' => 'Lower Arch', 'additional_price' => 0, 'is_default' => false],
                                    ['name' => 'Both Arches', 'additional_price' => 300, 'is_default' => false],
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
