<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Visit endpoints over HTTP. The critical property under test: distinguishing the
 * provider's arrival claim from the customer's confirmation at the API boundary.
 */
class VisitHttpTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $customer = User::factory()->create();
        $providerAccount = User::factory()->create();
        $profile = ProfessionalProfile::factory()->create(['user_id' => $providerAccount->id]);

        $booking = Booking::factory()->create([
            'customer_user_id' => $customer->id,
            'professional_profile_id' => $profile->id,
            'state' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $visit = Visit::create([
            'booking_id' => $booking->id,
            'professional_profile_id' => $profile->id,
            'state' => 'scheduled',
        ]);

        return [$customer, $providerAccount, $visit];
    }

    public function test_arrive_endpoint_never_starts_billing(): void
    {
        [, $providerAccount, $visit] = $this->scenario();

        Sanctum::actingAs($providerAccount);
        $res = $this->postJson("/api/v1/visits/{$visit->public_id}/depart")->assertOk();
        $this->assertStringContainsString('en_route', $res->json('visit.state'));

        $arrive = $this->postJson("/api/v1/visits/{$visit->public_id}/arrive", [
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'accuracy_m' => 8,
        ])->assertOk();

        $this->assertNull($arrive->json('visit.session_started_at'));
        $this->assertNull($arrive->json('visit.billed_minutes'));
        $this->assertSame('geofence_is_a_signal_only_it_never_starts_billing', $arrive->json('note'));
    }

    public function test_customer_confirmation_starts_the_session(): void
    {
        [$customer, $providerAccount, $visit] = $this->scenario();

        Sanctum::actingAs($providerAccount);
        $this->postJson("/api/v1/visits/{$visit->public_id}/arrive", [
            'latitude' => 30.0444, 'longitude' => 31.2357,
        ])->assertOk();

        Sanctum::actingAs($customer);
        $confirmed = $this->postJson("/api/v1/visits/{$visit->public_id}/confirm-arrival")->assertOk();

        $this->assertNotNull($confirmed->json('visit.session_started_at'));
        $this->assertSame('in_session', $confirmed->json('visit.state'));
    }

    public function test_customer_cannot_confirm_before_provider_arrives(): void
    {
        [$customer, , $visit] = $this->scenario();

        Sanctum::actingAs($customer);
        $this->postJson("/api/v1/visits/{$visit->public_id}/confirm-arrival")
            ->assertStatus(409)
            ->assertJsonPath('code', 'provider_arrival_required');
    }

    public function test_unrelated_account_cannot_drive_the_visit(): void
    {
        [, , $visit] = $this->scenario();
        $stranger = User::factory()->create();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/visits/{$visit->public_id}/arrive")->assertStatus(403);
        $this->postJson("/api/v1/visits/{$visit->public_id}/confirm-arrival")->assertStatus(403);
        $this->postJson("/api/v1/visits/{$visit->public_id}/depart")->assertStatus(403);
    }
}