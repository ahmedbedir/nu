<?php

namespace Tests\Feature;

use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_submit_profile_and_credential_but_starts_pending(): void
    {
        $user = User::factory()->create();
        \App\Models\Profession::create([
            'code' => 'nurse',
            'name_ar' => 'ممرض',
            'name_en' => 'Nurse',
            'category' => 'nursing',
        ]);

        Sanctum::actingAs($user);

        $profile = $this->postJson('/api/v1/professionals/onboarding', [
            'profession_code' => 'nurse',
            'display_name' => 'نورا',
            'gender' => 'female',
            'years_experience_self_declared' => 8,
        ])->assertCreated();

        $profileId = $profile->json('profile.public_id');
        $this->assertSame('pending', $profile->json('profile.status'));

        $credential = $this->postJson('/api/v1/professionals/credentials', [
            'credential_type' => 'license',
            'title' => 'ترخيص مزاولة المهنة',
            'issuing_body' => 'وزارة الصحة',
            'number' => 'SECRET-LICENSE-123',
            'issued_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertCreated();

        $this->assertSame('pending', $credential->json('credential.status'));
        $this->assertDatabaseMissing('professional_credentials', ['number_ciphertext' => 'SECRET-LICENSE-123']);
    }

    public function test_reviewer_approves_credential_but_service_approval_remains_separate(): void
    {
        $professional = User::factory()->create();
        $reviewer = User::factory()->create();
        $profession = \App\Models\Profession::create([
            'code' => 'nurse',
            'name_ar' => 'ممرض',
            'name_en' => 'Nurse',
            'category' => 'nursing',
        ]);
        $service = Service::factory()->create(['profession_id' => $profession->id]);
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
            'profession_id' => $profession->id,
            'status' => 'pending',
        ]);
        $credential = ProfessionalCredential::factory()->unverified()->create([
            'professional_profile_id' => $profile->id,
        ]);

        $reviewRole = \App\Models\Role::firstOrCreate(['code' => 'reviewer'], [
            'name_ar' => 'مراجع', 'name_en' => 'Reviewer', 'scope' => 'staff', 'is_system' => true,
        ]);
        $permission = \App\Models\Permission::firstOrCreate(['code' => 'professionals.review'], [
            'name_ar' => 'مراجعة', 'name_en' => 'Review', 'group' => 'professionals',
        ]);
        $reviewRole->permissions()->sync([$permission->id]);
        $reviewer->roles()->attach($reviewRole->id);

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/v1/professional-credentials/'.$credential->public_id.'/review', [
            'verdict' => 'approved',
            'notes' => 'Documents checked',
        ])->assertOk()
            ->assertJsonPath('credential.status', 'verified')
            ->assertJsonPath('credential.profile_status', 'active');

        // Credential approval alone does not create a service-level approval.
        $this->assertDatabaseMissing('provider_service_approvals', [
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
        ]);
    }

    public function test_reviewer_cannot_approve_service_for_a_different_profession(): void
    {
        $reviewer = User::factory()->create();
        $professionA = \App\Models\Profession::create(['code' => 'nurse', 'name_ar' => 'ممرض', 'name_en' => 'Nurse', 'category' => 'nursing']);
        $professionB = \App\Models\Profession::create(['code' => 'lab', 'name_ar' => 'فني مختبر', 'name_en' => 'Lab', 'category' => 'lab']);
        $profile = ProfessionalProfile::factory()->create(['profession_id' => $professionA->id]);
        $service = Service::factory()->create(['profession_id' => $professionB->id]);

        $role = \App\Models\Role::create(['code' => 'reviewer', 'name_ar' => 'مراجع', 'name_en' => 'Reviewer', 'scope' => 'staff', 'is_system' => true]);
        $permission = \App\Models\Permission::create(['code' => 'professionals.review', 'name_ar' => 'مراجعة', 'name_en' => 'Review', 'group' => 'professionals']);
        $role->permissions()->attach($permission->id);
        $reviewer->roles()->attach($role->id);

        Sanctum::actingAs($reviewer);
        $this->postJson('/api/v1/provider-service-approvals', [
            'profile_public_id' => $profile->public_id,
            'service_code' => $service->code,
            'status' => 'approved',
        ])->assertStatus(422)->assertJsonPath('code', 'profession_mismatch');
    }
}