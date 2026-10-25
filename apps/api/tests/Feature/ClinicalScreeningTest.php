<?php

namespace Tests\Feature;

use App\Domain\Clinical\Services\EligibilityScreening;
use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-015 (red flag stops dispatch and gives emergency guidance, no AI diagnosis),
 * T-008 (UNKNOWN is explicit and blocks execution, not the booking), T-017 (age gate).
 */
class ClinicalScreeningTest extends TestCase
{
    use RefreshDatabase;

    private function screening(): EligibilityScreening
    {
        return app(EligibilityScreening::class);
    }

    public function test_hard_red_flag_stops_dispatch_and_returns_emergency_guidance(): void
    {
        $service = Service::factory()->create([
            'red_flag_screen' => [
                ['field' => 'chest_pain', 'op' => 'true', 'code' => 'cardiac_red_flag', 'severity' => 'hard'],
            ],
        ]);

        $patient = Patient::factory()->create();

        $result = $this->screening()->screen($service, $patient, ['chest_pain' => true]);

        $this->assertSame(EligibilityScreening::REJECTED_RED_FLAG, $result['outcome']);
        $this->assertContains('cardiac_red_flag', $result['reasons']);
        $this->assertSame('red_flag_emergency_guidance', $result['guidance']);
        $this->assertFalse($this->screening()->canDispatch($result));

        // No clinical diagnosis text is produced anywhere.
        $this->assertArrayNotHasKey('diagnosis', $result);
    }

    public function test_unknown_answer_is_explicit_and_blocks_execution_not_booking(): void
    {
        $service = Service::factory()->create([
            'required_fields' => ['allergies_known', 'weight_kg'],
        ]);

        $patient = Patient::factory()->create();

        $result = $this->screening()->screen($service, $patient, [
            'allergies_known' => 'UNKNOWN',
            'weight_kg' => 'UNKNOWN',
        ]);

        $this->assertSame(EligibilityScreening::REQUIRES_INFORMATION, $result['outcome']);
        $this->assertContains('allergies_known', $result['missing']);
        $this->assertContains('weight_kg', $result['missing']);
        $this->assertSame('provider_must_complete_before_execution', $result['guidance']);
        $this->assertFalse($this->screening()->canDispatch($result));
    }

    public function test_missing_mandatory_field_is_reported_not_assumed_false(): void
    {
        $service = Service::factory()->create(['required_fields' => ['weight_kg']]);
        $patient = Patient::factory()->create();

        $result = $this->screening()->screen($service, $patient, []);

        $this->assertSame(EligibilityScreening::REQUIRES_INFORMATION, $result['outcome']);
        $this->assertContains('weight_kg', $result['missing']);
    }

    public function test_complete_answers_on_a_simple_service_are_eligible(): void
    {
        $service = Service::factory()->create([
            'required_fields' => ['weight_kg'],
            'red_flag_screen' => [
                ['field' => 'fever_c', 'op' => 'gte', 'value' => 39, 'code' => 'high_fever', 'severity' => 'soft'],
            ],
        ]);
        $patient = Patient::factory()->create();

        $result = $this->screening()->screen($service, $patient, ['weight_kg' => 70, 'fever_c' => 37.2]);

        $this->assertSame(EligibilityScreening::ELIGIBLE, $result['outcome']);
        $this->assertTrue($this->screening()->canDispatch($result));
    }

    public function test_soft_rule_routes_to_qualified_review_instead_of_rejection(): void
    {
        $service = Service::factory()->create([
            'red_flag_screen' => [
                ['field' => 'fever_c', 'op' => 'gte', 'value' => 39, 'code' => 'high_fever', 'severity' => 'soft'],
            ],
        ]);
        $patient = Patient::factory()->create();

        $result = $this->screening()->screen($service, $patient, ['fever_c' => 39.5]);

        $this->assertSame(EligibilityScreening::REQUIRES_REVIEW, $result['outcome']);
        $this->assertSame('qualified_review_required', $result['guidance']);
    }

    public function test_T017_age_outside_the_approved_range_is_rejected(): void
    {
        $service = Service::factory()->create(['allowed_age_range' => ['min' => 18, 'max' => 65]]);
        $child = Patient::factory()->create(['birth_date' => now()->subYears(6)]);

        $result = $this->screening()->screen($service, $child, []);

        $this->assertSame(EligibilityScreening::REJECTED_RED_FLAG, $result['outcome']);
        $this->assertContains('age_outside_service_range', $result['reasons']);
    }

    public function test_paediatric_service_allows_a_child_inside_the_range(): void
    {
        $service = Service::factory()->create(['allowed_age_range' => ['min' => 0, 'max' => 12]]);
        $child = Patient::factory()->create(['birth_date' => now()->subYears(6)]);

        $this->assertSame(
            EligibilityScreening::ELIGIBLE,
            $this->screening()->screen($service, $child, [])['outcome'],
        );
    }
}