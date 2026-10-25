<?php

namespace App\Console\Commands;

use App\Models\Profession;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;

/**
 * Idempotent starter catalogue.
 *
 * Creates professions, categories and a small set of nursing services. By default
 * services are created as **draft**, mirroring the rule that only a licensed medical
 * reviewer may publish a clinical service (#65). `--publish` exists purely so a local
 * preview environment can render the catalogue; it is never a substitute for the
 * clinical sign-off gate before any public launch.
 */
class SeedCatalogCommand extends Command
{
    protected $signature = 'homecare:seed-catalog {--publish : mark starter services as published (local preview only)}';

    protected $description = 'Create the starter professions, categories and nursing services (idempotent)';

    public function handle(): int
    {
        $professions = [
            ['code' => 'nurse', 'name_ar' => 'ممرض/ممرضة', 'name_en' => 'Nurse', 'category' => 'nursing'],
            ['code' => 'care_assistant', 'name_ar' => 'مساعد رعاية', 'name_en' => 'Care assistant', 'category' => 'personal_care'],
            ['code' => 'lab_technician', 'name_ar' => 'فني مختبر', 'name_en' => 'Lab technician', 'category' => 'lab'],
        ];

        foreach ($professions as $row) {
            Profession::updateOrCreate(['code' => $row['code']], $row);
        }

        $nurse = Profession::query()->where('code', 'nurse')->firstOrFail();

        $categories = [
            ['code' => 'nursing', 'name_ar' => 'تمريض منزلي', 'name_en' => 'Home nursing'],
            ['code' => 'personal_care', 'name_ar' => 'رعاية يومية غير طبية', 'name_en' => 'Non-medical personal care'],
            ['code' => 'wound_care', 'name_ar' => 'العناية بالجروح', 'name_en' => 'Wound care'],
        ];

        foreach ($categories as $row) {
            ServiceCategory::updateOrCreate(['code' => $row['code']], $row);
        }

        $nursing = ServiceCategory::query()->where('code', 'nursing')->firstOrFail();
        $wound = ServiceCategory::query()->where('code', 'wound_care')->firstOrFail();

        $status = $this->option('publish') ? 'published' : 'draft';

        $services = [
            [
                'code' => 'vitals_check',
                'category_id' => $nursing->id,
                'name_ar' => 'قياس العلامات الحيوية',
                'name_en' => 'Vital signs check',
                'description_ar' => 'قياس ضغط الدم والنبض والحرارة ونسبة الأكسجين في المنزل مع تسجيل النتائج.',
                'description_en' => 'Blood pressure, pulse, temperature and oxygen saturation measured at home, with results recorded.',
                'billing_mode' => 'fixed',
                'default_duration_minutes' => 30,
                'base_price' => 250.00,
                'requires_medical_order' => false,
            ],
            [
                'code' => 'injection_im',
                'category_id' => $nursing->id,
                'name_ar' => 'حقن عضلي',
                'name_en' => 'Intramuscular injection',
                'description_ar' => 'إعطاء حقنة عضلية بوصفة طبية سارية، مع مراجعة الجرعة قبل التنفيذ.',
                'description_en' => 'Administration of an intramuscular injection against a valid prescription, with dose review before execution.',
                'billing_mode' => 'fixed',
                'default_duration_minutes' => 30,
                'base_price' => 300.00,
                'requires_medical_order' => true,
            ],
            [
                'code' => 'wound_dressing',
                'category_id' => $wound->id,
                'name_ar' => 'غيار جروح بسيط',
                'name_en' => 'Simple wound dressing',
                'description_ar' => 'تنظيف وتغيير غيار جرح بسيط مع تقييم حالة الجرح والتوثيق.',
                'description_en' => 'Cleaning and re-dressing a simple wound with assessment and documentation.',
                'billing_mode' => 'fixed',
                'default_duration_minutes' => 45,
                'base_price' => 400.00,
                'requires_medical_order' => false,
            ],
            [
                'code' => 'elderly_care_shift',
                'category_id' => $nursing->id,
                'name_ar' => 'رعاية مسنين بالساعة',
                'name_en' => 'Hourly elderly care',
                'description_ar' => 'متابعة مريض مستقر بالساعة، والحد الأدنى للاحتساب 30 دقيقة ثم تُحاسب الدقائق الفعلية.',
                'description_en' => 'Hourly monitoring of a stable patient. Billing minimum is 30 minutes, then actual minutes.',
                'billing_mode' => 'time',
                'default_duration_minutes' => 120,
                'base_price' => 180.00,
                'requires_medical_order' => false,
            ],
        ];

        foreach ($services as $row) {
            $row['profession_id'] = $nurse->id;
            $row['status'] = $status;
            $row['currency'] = 'EGP';

            $service = Service::updateOrCreate(['code' => $row['code']], $row);

            if ($status === 'published' && $service->published_at === null) {
                $service->update(['published_at' => now()]);
            }
        }

        $this->info(sprintf(
            'Catalogue ready: %d professions, %d categories, %d services (%s).',
            count($professions),
            count($categories),
            count($services),
            $status,
        ));

        if ($status !== 'published') {
            $this->warn('Services are DRAFT. A licensed medical reviewer must publish them (#65).');
        }

        return self::SUCCESS;
    }
}