<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PatientDelegation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-003 (forged patient id denied, no enumeration) and
 * T-004 (scoped delegation — "book" scope cannot read the medical record; revoke is immediate).
 */
class PatientAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_and_read_their_patient(): void
    {
        $owner = User::factory()->create();

        $created = $this->actingAs($owner)
            ->postJson('/api/v1/patients', [
                'full_name' => 'أم محمد',
                'sex' => 'female',
                'relationship' => 'self',
            ]);

        $created->assertCreated();
        $publicId = $created->json('patient.public_id');
        $this->assertNotEmpty($publicId);

        $this->actingAs($owner)
            ->getJson('/api/v1/patients/'.$publicId)
            ->assertOk()
            ->assertJsonPath('patient.full_name', 'أم محمد');
    }

    public function test_T003_other_account_cannot_read_patient_even_with_known_id(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $patient = Patient::factory()->create(['created_by' => $owner->id]);

        // Direct access with the real public id is denied.
        $this->actingAs($attacker)
            ->getJson('/api/v1/patients/'.$patient->public_id)
            ->assertStatus(403);

        // And the patient never appears in the attacker's listing.
        $this->actingAs($attacker)
            ->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_T003_guest_cannot_list_patients(): void
    {
        $this->getJson('/api/v1/patients')->assertStatus(401);
    }

    public function test_T004_book_scope_cannot_read_medical_record_and_revoke_is_immediate(): void
    {
        $owner = User::factory()->create();
        $caregiver = User::factory()->withPhone('+201011112222')->create();

        $patient = Patient::factory()->create(['created_by' => $owner->id]);
        $patient->allergies()->create(['allergen' => 'Penicillin', 'severity' => 'severe']);

        // Grant book-only scope.
        $this->actingAs($owner)
            ->postJson('/api/v1/patients/'.$patient->public_id.'/delegations', [
                'grantee_phone' => '+201011112222',
                'scopes' => ['book'],
            ])
            ->assertCreated();

        // Book-only scope grants neither summary nor medical access.
        $this->actingAs($caregiver)
            ->getJson('/api/v1/patients/'.$patient->public_id)
            ->assertStatus(403);

        // Upgrade to view_visit_status → summary visible, clinical still hidden.
        $this->actingAs($owner)
            ->postJson('/api/v1/patients/'.$patient->public_id.'/delegations', [
                'grantee_phone' => '+201011112222',
                'scopes' => ['book', 'view_visit_status'],
            ])
            ->assertCreated();

        $this->actingAs($caregiver)
            ->getJson('/api/v1/patients/'.$patient->public_id)
            ->assertOk()
            ->assertJsonMissingPath('patient.clinical');

        // View medical scope reveals the clinical block.
        $this->actingAs($owner)
            ->postJson('/api/v1/patients/'.$patient->public_id.'/delegations', [
                'grantee_phone' => '+201011112222',
                'scopes' => ['view_medical'],
            ])
            ->assertCreated();

        $this->actingAs($caregiver)
            ->getJson('/api/v1/patients/'.$patient->public_id)
            ->assertOk()
            ->assertJsonPath('patient.clinical.allergies.0.allergen', 'Penicillin');

        // Revoke → access blocked immediately.
        $delegationId = PatientDelegation::query()
            ->where('patient_id', $patient->id)
            ->where('grantee_user_id', $caregiver->id)
            ->firstOrFail()->public_id;

        $this->actingAs($owner)
            ->deleteJson('/api/v1/patients/'.$patient->public_id.'/delegations/'.$delegationId)
            ->assertOk();

        $this->actingAs($caregiver)
            ->getJson('/api/v1/patients/'.$patient->public_id)
            ->assertStatus(403);
    }

    public function test_delegation_cannot_be_granted_to_unregistered_phone(): void
    {
        $owner = User::factory()->create();
        $patient = Patient::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($owner)
            ->postJson('/api/v1/patients/'.$patient->public_id.'/delegations', [
                'grantee_phone' => '+201099999999',
                'scopes' => ['book'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'grantee_not_found');
    }

    public function test_creator_link_is_verified_immediately(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson('/api/v1/patients', [
                'full_name' => 'سلمى',
                'sex' => 'female',
                'relationship' => 'self',
            ])->assertCreated();

        $this->assertDatabaseHas('patient_user_links', [
            'user_id' => $owner->id,
            'relationship' => 'self',
            'verification_status' => 'verified',
        ]);
    }
}