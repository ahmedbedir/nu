<?php

namespace Database\Factories;

use App\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProfessionalProfile>
 */
class ProfessionalCredentialFactory extends Factory
{
    protected $model = \App\Models\ProfessionalCredential::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'professional_profile_id' => ProfessionalProfile::factory(),
            'credential_type' => 'license',
            'title' => 'ترخيص مزاولة المهنة',
            'issuing_body' => 'وزارة الصحة والسكان',
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addYear(),
            'status' => 'verified',
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
            'status' => 'verified',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}