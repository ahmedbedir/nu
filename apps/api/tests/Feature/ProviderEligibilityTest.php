<?php

namespace Tests\Feature;

use App\Domain\Professionals\Services\ProviderEligibility;
use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\ProviderServiceApproval;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-011 (unverified/expired licence gets zero eligibility) and
 * T-012 (a nurse approved only for service A is never eligible for B).
 */
class ProviderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function eligibility(): ProviderEligibility
    {
        return app(ProviderEligibility::class);
    }

    public function test_T011_profile_with_no_valid_credential_is_never_eligible(): void
    {
        $service = Service::factory()->create();
        $profile = ProfessionalProfile::factory()->create([
            'profession_id' => $service->profession_id,
        ]);

        // Approved for the service, but no credential at all.
        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $result = $this->eligibility()->check($profile, $service);

        $this->assertFalse($result['eligible']);
        $this->assertContains('no_valid_credential', $result['reasons']);
    }

    public function test_T011_expired_licence_blocks_eligibility_even_when_approved(): void
    {
        $service = Service::factory()->create();
        $profile = ProfessionalProfile::factory()->create(['profession_id' => $service->profession_id]);

        ProfessionalCredential::factory()->expired()->create([
            'professional_profile_id' => $profile->id,
        ]);

        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertFalse($this->eligibility()->isEligible($profile, $service));
    }

    public function test_unverified_licence_does_not_count_as_valid(): void
    {
        $service = Service::factory()->create();
        $profile = ProfessionalProfile::factory()->create(['profession_id' => $service->profession_id]);

        ProfessionalCredential::factory()->unverified()->create([
            'professional_profile_id' => $profile->id,
        ]);

        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertFalse($this->eligibility()->isEligible($profile, $service));
    }

    public function test_T012_approved_for_service_A_is_not_eligible_for_service_B(): void
    {
        $serviceA = Service::factory()->create();
        $serviceB = Service::factory()->create(['profession_id' => $serviceA->profession_id]);

        $profile = ProfessionalProfile::factory()->create(['profession_id' => $serviceA->profession_id]);
        ProfessionalCredential::factory()->create(['professional_profile_id' => $profile->id]);

        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $serviceA->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertTrue($this->eligibility()->isEligible($profile, $serviceA));
        $this->assertFalse($this->eligibility()->isEligible($profile, $serviceB));
    }

    public function test_suspended_profile_is_not_eligible_even_with_valid_licence(): void
    {
        $service = Service::factory()->create();
        $profile = ProfessionalProfile::factory()->create([
            'profession_id' => $service->profession_id,
            'status' => 'suspended',
        ]);

        ProfessionalCredential::factory()->create(['professional_profile_id' => $profile->id]);
        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertFalse($this->eligibility()->isEligible($profile, $service));
    }

    public function test_unpublished_service_is_not_bookable(): void
    {
        $service = Service::factory()->draft()->create();
        $profile = ProfessionalProfile::factory()->create(['profession_id' => $service->profession_id]);

        ProfessionalCredential::factory()->create(['professional_profile_id' => $profile->id]);
        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $result = $this->eligibility()->check($profile, $service);

        $this->assertFalse($result['eligible']);
        $this->assertContains('service_not_published', $result['reasons']);
    }
}