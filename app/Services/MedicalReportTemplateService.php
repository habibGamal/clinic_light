<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\Service;

final class MedicalReportTemplateService
{
    public const TYPE_RADIOLOGY = 'radiology';

    public const TYPE_CBCT = 'cbct';

    public const TYPE_DIGITAL_SCAN = 'digital_scan';

    public const TYPE_GENERAL = 'general';

    /**
     * @return array<string, string>
     */
    public static function getTemplateTypes(): array
    {
        return [
            self::TYPE_RADIOLOGY => 'قالب فحص الأشعة السينية (2D / OPG / X-Ray)',
            self::TYPE_CBCT => 'قالب الأشعة المقطعية المخروطية (3D CBCT)',
            self::TYPE_DIGITAL_SCAN => 'قالب المسح والتصميم الرقمي والأدلة الجراحية (Digital / Guides)',
            self::TYPE_GENERAL => 'قالب تقرير طبي وسريري عام (General Clinical)',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getSavedTemplatesOptions(?int $serviceId = null): array
    {
        return ReportTemplate::query()
            ->where('is_active', true)
            ->when($serviceId, function ($query, $serviceId): void {
                $query->where(function ($sub) use ($serviceId): void {
                    $sub->where('service_id', $serviceId)
                        ->orWhereNull('service_id');
                });
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public static function determineTemplateTypeForService(?Service $service): string
    {
        if (! $service) {
            return self::TYPE_GENERAL;
        }

        $serviceName = mb_strtolower($service->name);
        $categoryName = mb_strtolower($service->serviceCategory?->name ?? '');
        $categoryCode = mb_strtolower($service->serviceCategory?->code ?? '');

        if (str_contains($serviceName, 'cbct') || str_contains($serviceName, 'cone beam') || str_contains($categoryName, '3d')) {
            return self::TYPE_CBCT;
        }

        if (
            str_contains($serviceName, 'scan') ||
            str_contains($serviceName, 'guide') ||
            str_contains($serviceName, 'dsd') ||
            str_contains($serviceName, 'smile') ||
            str_contains($categoryName, 'digital') ||
            $categoryCode === 'dig'
        ) {
            return self::TYPE_DIGITAL_SCAN;
        }

        if (
            str_contains($serviceName, 'x-ray') ||
            str_contains($serviceName, 'opg') ||
            str_contains($serviceName, 'panoramic') ||
            str_contains($serviceName, 'ceph') ||
            str_contains($serviceName, 'periapical') ||
            str_contains($serviceName, 'bitewing') ||
            str_contains($categoryName, '2d') ||
            $categoryCode === '2d'
        ) {
            return self::TYPE_RADIOLOGY;
        }

        return self::TYPE_GENERAL;
    }

    public static function generateTemplate(?Service $service, ?string $typeOrTemplateId = null): string
    {
        $serviceName = $service?->name ?? 'الفحص الطبي المختار';

        if ($typeOrTemplateId && is_numeric($typeOrTemplateId)) {
            $saved = ReportTemplate::query()->find((int) $typeOrTemplateId);
            if ($saved) {
                return $saved->getFormattedContent($serviceName);
            }
        }

        if (! $typeOrTemplateId && $service) {
            $customServiceTemplate = ReportTemplate::query()
                ->where('service_id', $service->id)
                ->where('is_active', true)
                ->first();

            if ($customServiceTemplate) {
                return $customServiceTemplate->getFormattedContent($serviceName);
            }
        }

        $type = $typeOrTemplateId ?? self::determineTemplateTypeForService($service);

        return match ($type) {
            self::TYPE_CBCT => self::cbctTemplate($serviceName),
            self::TYPE_RADIOLOGY => self::radiologyTemplate($serviceName),
            self::TYPE_DIGITAL_SCAN => self::digitalScanTemplate($serviceName),
            default => self::generalTemplate($serviceName),
        };
    }

    private static function cbctTemplate(string $serviceName): string
    {
        return <<<HTML
<h3><strong>تقرير فحص الأشعة المقطعية ثلاثية الأبعاد: {$serviceName}</strong></h3>
<p><strong>الخدمة المطلوبة:</strong> {$serviceName}</p>
<p><strong>الداعي السريري / الشكوى (Clinical Indication):</strong> [ تقييم زراعة / تقييم ضرس عقل مطمور / معالجة لبية معقدة / آفة عظمية ]</p>
<p><strong>منطقة الدراسة (Field of View - FOV):</strong> [ كامل الفكين (Dual Jaw) / الفك العلوي / الفك السفلي / منطقة محددة: ... ]</p>

<hr />

<h4><strong>المشاهدات والنتائج التشخيصية ثلاثية الأبعاد (3D CBCT Findings):</strong></h4>
<ul>
    <li><strong>الأبعاد العظمية ومواقع الزراعة المقترحة (Bone Dimensions & Quality):</strong>
        <br />- الارتفاع العظمي المتاح: [ ......... ملم ]
        <br />- العرض العظمي السنخي: [ ......... ملم ]
        <br />- الكثافة العظمية والنوع: [ D1 / D2 / D3 / D4 ]
    </li>
    <li><strong>العلاقة مع البنى التشريحية الحيوية (Anatomical Landmarks):</strong>
        <br />- العصب السنخي السفلي (IAC): [ المسافة عن قمة السنخ: ......... ملم / علاقة جذر الضرس المطمور بالقناة: ... ]
        <br />- الجيوب الفكية (Maxillary Sinuses): [ تهوية طبيعية / سماكة غشاء مخاطي بمقدار: ......... ملم ]
        <br />- القناة القاطعة والأنفية: [ طبيعية / لا توجد استطالة أو توسع ]
    </li>
    <li><strong>حالة الأسنان والجذور المجاورة (Teeth & Root Morphology):</strong>
        <br />[ عدد القنوات الجذرية، آفات ذروية، كسور جذرية شاقولية، ارتشاف جذري: ... ]
    </li>
    <li><strong>الآفات العظمية وغير الطبيعية (Bony Pathology / Lesions):</strong>
        <br />[ لا توجد آفات عظمية أو تكيسات ظاهرة / تم رصد شفافية شعاعية محددة في منطقة: ... ]
    </li>
</ul>

<hr />

<h4><strong>الخلاصة والتشخيص النهائي (Impression / Conclusion):</strong></h4>
<p>[ اكتب الخلاصة التشخيصية هنا ... ]</p>

<hr />

<h4><strong>التوصيات والخطة المقترحة (Recommendations):</strong></h4>
<p>[ إمكانية الزراعة الفورية / الحاجة لرفع جيب فكي أو تطعيم عظمي / توخي الحذر لقرب القناة العصبية / خلع جراحي ]</p>
HTML;
    }

    private static function radiologyTemplate(string $serviceName): string
    {
        return <<<HTML
<h3><strong>تقرير فحص الأشعة السينية: {$serviceName}</strong></h3>
<p><strong>الخدمة المفحوصة:</strong> {$serviceName}</p>
<p><strong>الشكوى السريرية / الداعي للفحص (Clinical Indication):</strong> [ فحص دوري شامل / ألم حاد / تسوسات متعددة / فحص تقويمي ]</p>
<p><strong>المنطقة / الأسنان المفحوصة (Region of Interest):</strong> [ الفكين كاملين / سن رقم: ... ]</p>

<hr />

<h4><strong>المشاهدات والنتائج الشعاعية (Radiographic Findings):</strong></h4>
<ul>
    <li><strong>حالة الأسنان والترميمات (Dentition & Restorations):</strong>
        <br />[ تسوسات ما بين الأسنان، معالجات لبية سابقة، أسنان مطمورة: ... ]
    </li>
    <li><strong>مستوى العظم والأنسجة حول السنية (Bone Level & Periodontium):</strong>
        <br />[ مستوى عظمي سليم / امتصاص عظمي أفقي/عمودي طفيف / متوسط / متقدم في منطقة: ... ]
    </li>
    <li><strong>الجيوب الفكية وقاع التجويف الأنفي (Maxillary Sinuses):</strong>
        <br />[ شفافة وطبيعية وخالية من الكثافات أو الشفافيات الشاذة ]
    </li>
    <li><strong>المفصل الفكي الصدغي (TMJ Condyles):</strong>
        <br />[ رؤوس لقم الفك السفلي متناظرة ذات محيط أملس وطبيعي ]
    </li>
    <li><strong>ملاحظات وتشوهات أخرى (Other Observations):</strong>
        <br />[ لا توجد مظاهر مرضية عظمية أو سنية أخرى غير معتادة ]
    </li>
</ul>

<hr />

<h4><strong>الخلاصة والتشخيص النهائي (Impression / Conclusion):</strong></h4>
<p>[ اكتب التشخيص النهائي والموجز هنا ... ]</p>

<hr />

<h4><strong>التوصيات (Recommendations):</strong></h4>
<p>[ متابعة سريرية / معالجة لبية للسن ... / قلع الضرس المطمور ... / استشارة أخصائي لثة ]</p>
HTML;
    }

    private static function digitalScanTemplate(string $serviceName): string
    {
        return <<<HTML
<h3><strong>تقرير المسح الرقمي والتخطيط: {$serviceName}</strong></h3>
<p><strong>نوع الإجراء الرقمي:</strong> {$serviceName}</p>
<p><strong>الهدف السريري من الإجراء (Clinical Objective):</strong> [ زراعة أسنان موجهة / تصميم ابتسامة DSD / تقويم شفاف / دليل جراحي ]</p>
<p><strong>الفك / المنطقة المستهدفة (Target Arch):</strong> [ الفك العلوي / الفك السفلي / كلا الفكين والعضة ]</p>

<hr />

<h4><strong>ملاحظات المسح والتحليل الرقمي (Digital Scan & Design Notes):</strong></h4>
<ul>
    <li><strong>جودة ودقة المسح ثلاثي الأبعاد (Scan Quality & Accuracy):</strong>
        <br />[ مسح رقمي عالي الدقة، خالي من التشوهات أو الفجوات في الحواف السنية واللثوية ]
    </li>
    <li><strong>العلاقة الإطباقية وتسجيل العضة (Occlusal Record & Alignment):</strong>
        <br />[ تطابق إطباقي سليم ومستقر / تم التقاط العضة المعتادة بنجاح ]
    </li>
    <li><strong>الأنسجة الرخوة والمعالم التشريحية (Soft Tissue Architecture):</strong>
        <br />[ معالم اللثة ملتقطة بوضوح ومناسبة لتحديد خط الابتسامة ومواقع الانبثاق ]
    </li>
    <li><strong>مواصفات التخطيط أو الدليل الجراحي (Planning & Guide Specifications):</strong>
        <br />[ تم تحديد موقع وزاوية الزرعات / تم توليد الدليل الجراحي بالمعايير المطلوبة ]
    </li>
</ul>

<hr />

<h4><strong>الاعتماد والخلاصة (Status / Approval):</strong></h4>
<p>[ التصميم الرقمي معتمد وجاهز للتصدير والطباعة ثلاثية الأبعاد (Ready for 3D Printing / Milling) ]</p>

<hr />

<h4><strong>الخطوات التالية والتوصيات (Next Steps):</strong></h4>
<p>[ طباعة الدليل الجراحي وتجريبه / جلسة مناقشة الابتسامة الرقمية مع المريض ]</p>
HTML;
    }

    private static function generalTemplate(string $serviceName): string
    {
        return <<<HTML
<h3><strong>تقرير طبي تشخيصي: {$serviceName}</strong></h3>
<p><strong>الخدمة الطبية:</strong> {$serviceName}</p>
<p><strong>الشكوى الرئيسية (Chief Complaint):</strong> [ اكتب شكوى المريض الرئيسية هنا ... ]</p>
<p><strong>الفحص الموضعي والسريري (Clinical Examination):</strong> [ نتائج الفحص السريري المباشر ... ]</p>

<hr />

<h4><strong>المشاهدات والنتائج (Findings):</strong></h4>
<p>[ اكتب تفاصيل النتائج والمشاهدات السريرية ... ]</p>

<hr />

<h4><strong>التشخيص النهائي (Diagnosis / Impression):</strong></h4>
<p>[ اكتب التشخيص النهائي هنا ... ]</p>

<hr />

<h4><strong>الخطة العلاجية والتوصيات (Recommendations / Treatment Plan):</strong></h4>
<p>[ الإجراءات الموصى بها / الوصفة الدوائية / موعد المتابعة القادم ... ]</p>
HTML;
    }
}
