<?php

declare(strict_types=1);

use App\Filament\Resources\PatientVisits\PatientVisitResource;
use App\Filament\Resources\PatientVisits\RelationManagers\ReportsRelationManager;
use App\Filament\Resources\PatientVisits\RelationManagers\VisitServicesRelationManager;
use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Patient;
use App\Models\PatientVisit;
use App\Models\Report;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VisitService;
use App\Services\MedicalReportTemplateService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('can render reports list page', function (): void {
    livewire(ListReports::class)
        ->assertOk();
});

it('can render report create page and generates default general template if no service is selected', function (): void {
    livewire(CreateReport::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'user_id' => $this->user->id,
        ]);
});

it('pre-selects service, fills title and prebuilds template with blanks when visit_id is provided', function (): void {
    $patient = Patient::factory()->create(['full_name' => 'أحمد محمود']);
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);

    $category = ServiceCategory::query()->firstOrCreate(
        ['code' => '2D'],
        ['name' => '2D Radiography']
    );

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Panoramic X-ray (OPG)',
        'code' => 'OPG-01',
        'base_price' => 150,
        'cost' => 50,
        'is_active' => true,
    ]);

    $visitService = VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
    ]);

    request()->merge([
        'visit_id' => $visit->id,
    ]);

    $component = livewire(CreateReport::class, [
        'visit_id' => $visit->id,
    ])
        ->assertOk();

    $state = $component->get('data');

    expect((int) $state['visit_service_id'])->toBe($visitService->id)
        ->and($state['title'])->toBe('تقرير طبي - Panoramic X-ray (OPG)');

    $reportText = is_array($state['report_text']) ? json_encode($state['report_text'], JSON_UNESCAPED_UNICODE) : (string) $state['report_text'];
    expect($reportText)
        ->toContain('Panoramic X-ray (OPG)')
        ->toContain('Findings')
        ->toContain('[');
});

it('updates title and rich editor template dynamically when a new service is selected', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);

    $category = ServiceCategory::query()->firstOrCreate(
        ['code' => '3D'],
        ['name' => '3D Radiography']
    );

    $cbctService = Service::query()->create([
        'category_id' => $category->id,
        'name' => '3D Cone Beam CT (CBCT)',
        'code' => 'CBCT-01',
        'base_price' => 500,
        'cost' => 100,
        'is_active' => true,
    ]);

    $vsCbct = VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $cbctService->id,
    ]);

    $component = livewire(CreateReport::class, [
        'visit_id' => $visit->id,
    ])
        ->set('data.visit_service_id', $vsCbct->id)
        ->assertSet('data.title', 'تقرير طبي - 3D Cone Beam CT (CBCT)');

    $text = $component->get('data.report_text');
    $asString = is_array($text) ? json_encode($text, JSON_UNESCAPED_UNICODE) : (string) $text;

    expect($asString)
        ->toContain('تقرير فحص الأشعة المقطعية ثلاثية الأبعاد')
        ->toContain('3D Cone Beam CT (CBCT)');
});

it('can create a report and redirects to the patient visit edit page when visit_id is present', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::query()->first() ?? Service::factory()->create();

    $visitService = VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
    ]);

    livewire(CreateReport::class, [
        'visit_id' => $visit->id,
    ])
        ->fillForm([
            'visit_service_id' => $visitService->id,
            'user_id' => $this->user->id,
            'title' => 'تقرير فحص نهائي',
            'report_text' => '<p>النتائج: حالة الأسنان سليمة تماماً.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(PatientVisitResource::getUrl('edit', ['record' => $visit->id]));

    assertDatabaseHas(Report::class, [
        'visit_service_id' => $visitService->id,
        'user_id' => $this->user->id,
        'title' => 'تقرير فحص نهائي',
    ]);
});

it('provides link to create report page in ReportsRelationManager header action', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);

    $livewire = livewire(ReportsRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => App\Filament\Resources\PatientVisits\Pages\EditPatientVisit::class,
    ]);

    $createAction = collect($livewire->instance()->getTable()->getHeaderActions())
        ->first(fn ($action) => $action->getName() === 'create');

    expect($createAction)->not->toBeNull()
        ->and($createAction->getUrl())->toBe(ReportResource::getUrl('create', ['visit_id' => $visit->id]));
});

it('provides writeReport row action in VisitServicesRelationManager', function (): void {
    $patient = Patient::factory()->create();
    $visit = PatientVisit::factory()->create(['patient_id' => $patient->id]);
    $service = Service::query()->first() ?? Service::factory()->create();

    $visitService = VisitService::factory()->create([
        'visit_id' => $visit->id,
        'service_id' => $service->id,
    ]);

    $livewire = livewire(VisitServicesRelationManager::class, [
        'ownerRecord' => $visit,
        'pageClass' => App\Filament\Resources\PatientVisits\Pages\EditPatientVisit::class,
    ]);

    $writeAction = collect($livewire->instance()->getTable()->getActions())
        ->first(fn ($action) => $action->getName() === 'writeReport');

    expect($writeAction)->not->toBeNull()
        ->and($writeAction->record($visitService)->getUrl())->toBe(ReportResource::getUrl('create', [
            'visit_id' => $visit->id,
            'visit_service_id' => $visitService->id,
        ]));
});

it('generates specialized templates according to service type in MedicalReportTemplateService', function (): void {
    $serviceCbct = new Service(['name' => '3D Cone Beam CT (CBCT)']);
    $templateCbct = MedicalReportTemplateService::generateTemplate($serviceCbct);
    expect($templateCbct)->toContain('3D Cone Beam CT (CBCT)')
        ->and($templateCbct)->toContain('الأبعاد العظمية ومواقع الزراعة المقترحة')
        ->and($templateCbct)->toContain('[');

    $serviceScan = new Service(['name' => 'Intra-Oral Scan']);
    $templateScan = MedicalReportTemplateService::generateTemplate($serviceScan);
    expect($templateScan)->toContain('Intra-Oral Scan')
        ->and($templateScan)->toContain('جودة ودقة المسح ثلاثي الأبعاد')
        ->and($templateScan)->toContain('[');

    $serviceOpg = new Service(['name' => 'Panoramic X-ray (OPG)']);
    $templateOpg = MedicalReportTemplateService::generateTemplate($serviceOpg);
    expect($templateOpg)->toContain('Panoramic X-ray (OPG)')
        ->and($templateOpg)->toContain('المشاهدات والنتائج الشعاعية')
        ->and($templateOpg)->toContain('[');
});

it('allows users to create custom templates and use them in medical reports', function (): void {
    $category = ServiceCategory::query()->firstOrCreate(
        ['code' => 'TEST'],
        ['name' => 'Test Category']
    );

    $service = Service::query()->create([
        'category_id' => $category->id,
        'name' => 'Custom Extraction Exam',
        'code' => 'EXT-01',
        'base_price' => 100,
        'cost' => 20,
        'is_active' => true,
    ]);

    $template = App\Models\ReportTemplate::query()->create([
        'name' => 'قالب فحص خلع خاص بالدكتور',
        'service_id' => $service->id,
        'content' => '<h4>فحص مخصص لخدمة: {service_name}</h4><p>حالة الجرح: [ طبيعي ومستقر ]</p>',
        'is_active' => true,
        'user_id' => $this->user->id,
    ]);

    // Test that the template formats content with the service name
    $formatted = $template->getFormattedContent($service->name);
    expect($formatted)->toContain('فحص مخصص لخدمة: Custom Extraction Exam')
        ->and($formatted)->toContain('[ طبيعي ومستقر ]');

    // Test that MedicalReportTemplateService automatically picks the custom template for this service
    $resolved = MedicalReportTemplateService::generateTemplate($service);
    expect($resolved)->toContain('Custom Extraction Exam')
        ->and($resolved)->toContain('[ طبيعي ومستقر ]');
});

it('can render report template resource pages', function (): void {
    livewire(App\Filament\Resources\ReportTemplates\Pages\ListReportTemplates::class)
        ->assertOk();

    livewire(App\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate::class)
        ->assertOk();
});
