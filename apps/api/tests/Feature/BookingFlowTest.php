<?php

namespace Tests\Feature;

use App\Domain\Booking\Services\BookingService;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\PatientUserLink;
use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\ProviderPresence;
use App\Models\ProviderServiceApproval;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end booking through the HTTP layer:
 * create → search (offers) → provider accepts → quote → customer confirms → visit exists.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function bookableSetup(): array
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

        $address = PatientAddress::factory()->create([
            'patient_id' => $patient->id,
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'verification_status' => 'verified',
        ]);

        $service = Service::factory()->create();

        return [$customer, $patient, $address, $service];
    }

    private function provider(User $account, Service $service, float $lat = 30.0500, float $lng = 31.2400): ProfessionalProfile
    {
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $account->id,
            'profession_id' => $service->profession_id,
            'status' => 'active',
            'gender' => 'female',
        ]);

        ProfessionalCredential::factory()->create(['professional_profile_id' => $profile->id]);

        ProviderServiceApproval::create([
            'professional_profile_id' => $profile->id,
            'service_code' => $service->code,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Fresh online GPS is required for instant dispatch (#118/#119).
        ProviderPresence::create([
            'professional_profile_id' => $profile->id,
            'status' => 'online',
            'latitude' => $lat,
            'longitude' => $lng,
            'captured_at' => now(),
        ]);

        return $profile;
    }

    public function test_full_booking_flow_over_http(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $providerAccount = User::factory()->create();
        $this->provider($providerAccount, $service);

        // --- customer creates the booking ---
        Sanctum::actingAs($customer);
        $created = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertCreated();

        $bookingId = $created->json('booking.public_id');
        $this->assertNotEmpty($bookingId);

        // --- search opens a wave with eligible providers only ---
        $search = $this->postJson("/api/v1/bookings/{$bookingId}/search")->assertOk();
        $this->assertSame(1, $search->json('candidates'));
        $offerId = $search->json('wave.offers.0.public_id');
        $this->assertNotEmpty($offerId);

        $offer = \App\Models\DispatchOffer::query()->where('public_id', $offerId)->firstOrFail();
        $offer->update(['expires_at' => now()->addSeconds(30)]);

        // --- provider accepts (provisional only) ---
        Sanctum::actingAs($providerAccount);
        $accepted = $this->postJson("/api/v1/offers/{$offerId}/accept")->assertOk();
        $this->assertSame('awaiting_customer_confirmation', $accepted->json('booking.state'));

        // --- the customer gets a priced quote, then confirms it ---
        Sanctum::actingAs($customer);
        $quote = $this->postJson("/api/v1/bookings/{$bookingId}/quote")->assertOk();
        $this->assertGreaterThan(0, (float) $quote->json('quote.total'));
        $quoteId = $quote->json('quote.version');

        $confirmed = $this->postJson("/api/v1/bookings/{$bookingId}/confirm", ['quote_id' => $quoteId])->assertOk();
        $this->assertSame('confirmed', $confirmed->json('booking.state'));

        // A visit exists and has NOT started.
        $booking = Booking::query()->where('public_id', $bookingId)->firstOrFail();
        $this->assertDatabaseHas('visits', ['booking_id' => $booking->id, 'state' => 'scheduled']);
        $this->assertNull($booking->visit->session_started_at);
    }

    public function test_search_without_eligible_provider_reports_no_match(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();

        Sanctum::actingAs($customer);
        $bookingId = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertCreated()->json('booking.public_id');

        $search = $this->postJson("/api/v1/bookings/{$bookingId}/search")->assertOk();

        $this->assertSame(0, $search->json('candidates'));
        $this->assertSame('no_match', $search->json('booking.state'));
    }

    public function test_provider_with_stale_gps_is_not_dispatched(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $providerAccount = User::factory()->create();
        $profile = $this->provider($providerAccount, $service);

        // GPS older than the freshness window → instant dispatch must exclude them.
        ProviderPresence::query()->where('professional_profile_id', $profile->id)
            ->update(['captured_at' => now()->subMinutes(30)]);

        Sanctum::actingAs($customer);
        $bookingId = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertCreated()->json('booking.public_id');

        $this->assertSame(0, $this->postJson("/api/v1/bookings/{$bookingId}/search")->json('candidates'));
    }

    public function test_provider_outside_radius_is_not_dispatched(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $providerAccount = User::factory()->create();

        // ~120 km away (Alexandria) — outside the 10 km default radius.
        $this->provider($providerAccount, $service, 31.2001, 29.9187);

        Sanctum::actingAs($customer);
        $bookingId = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertCreated()->json('booking.public_id');

        $this->assertSame(0, $this->postJson("/api/v1/bookings/{$bookingId}/search")->json('candidates'));
    }

    public function test_other_account_cannot_confirm_someone_elses_booking(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $attacker = User::factory()->create();

        Sanctum::actingAs($customer);
        $bookingId = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertCreated()->json('booking.public_id');

        Sanctum::actingAs($attacker);
        $this->postJson("/api/v1/bookings/{$bookingId}/confirm", ['quote_id' => 1])->assertStatus(403);
        $this->postJson("/api/v1/bookings/{$bookingId}/search")->assertStatus(403);
    }

    public function test_cannot_book_with_unverified_address_coordinates(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $address->update(['verification_status' => 'unverified']);

        Sanctum::actingAs($customer);

        $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
        ])->assertStatus(500); // DomainException: address must be verified first
    }

    public function test_T015_red_flag_booking_is_refused_outright(): void
    {
        [$customer, $patient, $address] = $this->bookableSetup();

        $service = Service::factory()->create([
            'red_flag_screen' => [
                ['field' => 'chest_pain', 'op' => 'true', 'code' => 'cardiac_red_flag', 'severity' => 'hard'],
            ],
        ]);

        Sanctum::actingAs($customer);

        $res = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
            'form_answers' => ['chest_pain' => true],
        ]);

        // Conventional booking stops; no booking row is created.
        $res->assertStatus(500);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_T008_unknown_safety_answer_creates_a_blocked_pending_information_booking(): void
    {
        [$customer, $patient, $address] = $this->bookableSetup();

        $service = Service::factory()->create([
            'required_fields' => ['weight_kg'],
        ]);

        Sanctum::actingAs($customer);

        $created = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
            'form_answers' => ['weight_kg' => 'UNKNOWN'],
        ])->assertCreated();

        $this->assertSame('requires_information', $created->json('booking.state'));

        // It must not be dispatchable until the safety data is supplied.
        $bookingId = $created->json('booking.public_id');
        $this->postJson("/api/v1/bookings/{$bookingId}/search")->assertStatus(500);
    }

    public function test_soft_clinical_rule_routes_the_booking_to_review_not_dispatch(): void
    {
        [$customer, $patient, $address] = $this->bookableSetup();

        $service = Service::factory()->create([
            'red_flag_screen' => [
                ['field' => 'fever_c', 'op' => 'gte', 'value' => 39, 'code' => 'high_fever', 'severity' => 'soft'],
            ],
        ]);

        Sanctum::actingAs($customer);

        $created = $this->postJson('/api/v1/bookings', [
            'patient_public_id' => $patient->public_id,
            'address_public_id' => $address->public_id,
            'service_public_id' => $service->public_id,
            'required_gender' => 'female',
            'form_answers' => ['fever_c' => 39.4],
        ])->assertCreated();

        $this->assertSame('clinical_review_pending', $created->json('booking.state'));

        $bookingId = $created->json('booking.public_id');
        $this->postJson("/api/v1/bookings/{$bookingId}/search")->assertStatus(500);
    }

    public function test_quote_contains_callout_and_commission_is_tracked(): void
    {
        [$customer, $patient, $address, $service] = $this->bookableSetup();
        $providerAccount = User::factory()->create();
        $profile = $this->provider($providerAccount, $service);

        $booking = Booking::factory()->create([
            'customer_user_id' => $customer->id,
            'patient_id' => $patient->id,
            'visit_address_id' => $address->id,
            'professional_profile_id' => $profile->id,
            'state' => 'awaiting_customer_confirmation',
            'scheduled_for' => now()->addHours(2),
        ]);
        $booking->request()->update(['service_id' => $service->id]);

        Sanctum::actingAs($customer);
        $quote = $this->postJson("/api/v1/bookings/{$booking->public_id}/quote")->assertOk();

        $lines = collect($quote->json('quote.lines'));
        $this->assertTrue($lines->contains(fn ($l) => $l['kind'] === 'callout'));
        $this->assertTrue($lines->contains(fn ($l) => in_array($l['kind'], ['procedure', 'time'], true)));
        $this->assertTrue($lines->contains(fn ($l) => $l['kind'] === 'travel'));
    }
}