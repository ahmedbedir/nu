<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\PatientUserLink;
use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\ProviderServiceApproval;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Address ownership, provider presence freshness and the offer inbox. */
class ProviderOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creates_a_verified_visit_address(): void
    {
        $customer = User::factory()->create();
        $patient = Patient::factory()->create(['created_by' => $customer->id]);
        PatientUserLink::create([
            'patient_id' => $patient->id,
            'user_id' => $customer->id,
            'relationship' => 'self',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $res = $this->postJson("/api/v1/patients/{$patient->public_id}/addresses", [
            'label' => 'المنزل',
            'address_line' => '١٢ شارع النيل، المعادي',
            'latitude' => 29.9602,
            'longitude' => 31.2569,
            'is_default' => true,
        ])->assertCreated();

        $this->assertSame('verified', $res->json('address.verification_status'));
        $this->assertTrue($res->json('address.is_default'));
        $this->assertDatabaseHas('patient_addresses', ['patient_id' => $patient->id]);
    }

    public function test_address_coordinates_outside_egypt_are_rejected(): void
    {
        $customer = User::factory()->create();
        $patient = Patient::factory()->create(['created_by' => $customer->id]);
        PatientUserLink::create([
            'patient_id' => $patient->id,
            'user_id' => $customer->id,
            'relationship' => 'self',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/patients/{$patient->public_id}/addresses", [
            'address_line' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ])->assertStatus(422);
    }

    public function test_unrelated_account_cannot_add_an_address_to_someone_elses_patient(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $patient = Patient::factory()->create(['created_by' => $owner->id]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/v1/patients/{$patient->public_id}/addresses", [
            'address_line' => 'x',
            'latitude' => 30.0,
            'longitude' => 31.2,
        ])->assertStatus(403);
    }

    public function test_going_online_without_coordinates_is_refused(): void
    {
        $account = User::factory()->create();
        ProfessionalProfile::factory()->create(['user_id' => $account->id]);

        Sanctum::actingAs($account);

        $this->putJson('/api/v1/professionals/presence', ['status' => 'online'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'fresh_location_required');
    }

    public function test_going_online_with_fresh_coordinates_marks_presence_fresh(): void
    {
        $account = User::factory()->create();
        ProfessionalProfile::factory()->create(['user_id' => $account->id]);

        Sanctum::actingAs($account);

        $res = $this->putJson('/api/v1/professionals/presence', [
            'status' => 'online',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ])->assertOk();

        $this->assertTrue($res->json('presence.fresh_for_instant'));
    }

    public function test_offer_inbox_only_lists_that_professionals_live_offers(): void
    {
        $service = Service::factory()->create();
        $providerAccount = User::factory()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $providerAccount->id,
            'profession_id' => $service->profession_id,
            'status' => 'active',
        ]);
        ProfessionalCredential::factory()->create(['professional_profile_id' => $profile->id]);
        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $otherAccount = User::factory()->create();
        ProfessionalProfile::factory()->create(['user_id' => $otherAccount->id]);

        Sanctum::actingAs($providerAccount);
        $inbox = $this->getJson('/api/v1/professionals/offers')->assertOk();

        $this->assertSame([], $inbox->json('data'));
    }
}