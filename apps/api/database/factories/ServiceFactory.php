<?php

namespace Database\Factories;

use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'category_id' => fn () => \App\Models\ServiceCategory::query()->firstOrCreate(
                ['code' => 'nursing'],
                ['name_ar' => 'تمريض', 'name_en' => 'Nursing'],
            )->id,
            'code' => 'svc_'.Str::lower(Str::random(8)),
            'name_ar' => 'خدمة تمريض منزلية',
            'name_en' => 'Home nursing service',
            'profession_id' => fn () => \App\Models\Profession::query()->firstOrCreate(
                ['code' => 'nurse'],
                ['name_ar' => 'ممرض', 'name_en' => 'Nurse', 'category' => 'nursing'],
            )->id,
            'billing_mode' => 'fixed',
            'default_duration_minutes' => 30,
            'status' => 'published',
            'requires_medical_order' => false,
            'base_price' => 300.00,
            'currency' => 'EGP',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}