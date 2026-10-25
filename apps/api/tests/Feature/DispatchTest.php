<?php

namespace Tests\Feature;

use App\Domain\Booking\Exceptions\OfferNotAvailableException;
use App\Domain\Booking\Services\DispatchService;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\DispatchOffer;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\ProfessionalCredential;
use App\Models\ProfessionalProfile;
use App\Models\ProviderServiceApproval;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-022 (offer TTL) and T-023 (concurrent accepts → exactly ONE winner).
 */
class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private function dispatch(): DispatchService
    {
        return app(DispatchService::class);
    }

    /** Build a bookable booking plus N eligible professionals. */
    private function scenario(int $providerCount = 2): array
    {
        $customer = User::factory()->create();
        $patient = Patient::factory()->create(['created_by' => $customer->id]);
        $address = PatientAddress::factory()->create(['patient_id' => $patient->id]);
        $service = Service::factory()->create();

        $request = BookingRequest::factory()->create([
            'customer_user_id' => $customer->id,
            'patient_id' => $patient->id,
            'visit_address_id' => $address->id,
            'service_id' => $service->id,
            'state' => 'searching',
        ]);

        $booking = Booking::factory()->create([
            'booking_request_id' => $request->id,
            'customer_user_id' => $customer->id,
            'patient_id' => $patient->id,
            'visit_address_id' => $address->id,
            'state' => 'searching',
            'scheduled_for' => now()->addHours(2),
        ]);

        $profiles = [];

        for ($i = 0; $i < $providerCount; $i++) {
            $profile = ProfessionalProfile::factory()->create([
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

            $profiles[] = $profile;
        }

        return [$booking, $profiles, $service];
    }

    public function test_T023_only_one_provider_wins_even_with_many_acceptors(): void
    {
        [$booking, $profiles] = $this->scenario(5);

        $wave = $this->dispatch()->openWave(
            $booking,
            array_map(fn ($p) => ['profile' => $p, 'eta_seconds' => 600], $profiles),
        );

        $this->assertCount(5, $wave->offers);

        $winners = 0;
        $losers = 0;

        foreach ($profiles as $profile) {
            $offer = DispatchOffer::query()
                ->where('booking_id', $booking->id)
                ->where('professional_profile_id', $profile->id)
                ->firstOrFail();

            try {
                $this->dispatch()->acceptOffer($offer, $profile);
                $winners++;
            } catch (OfferNotAvailableException) {
                $losers++;
            }
        }

        $this->assertSame(1, $winners, 'exactly one provider must win');
        $this->assertSame(4, $losers);

        // Exactly one ACTIVE hold exists for the booking.
        $this->assertSame(1, $booking->holds()->where('status', 'active')->count());

        // Provisional acceptance is NOT a confirmation.
        $this->assertSame('awaiting_customer_confirmation', $booking->fresh()->state);
        $this->assertNull($booking->fresh()->confirmed_at);
    }

    public function test_T022_expired_offer_cannot_be_accepted(): void
    {
        [$booking, $profiles] = $this->scenario(1);

        $wave = $this->dispatch()->openWave($booking, [['profile' => $profiles[0]]]);
        $offer = $wave->offers()->first();

        // Simulate the 30-second offer window elapsing.
        $offer->update(['expires_at' => now()->subSecond()]);

        $this->expectException(OfferNotAvailableException::class);
        $this->dispatch()->acceptOffer($offer->fresh(), $profiles[0]);
    }

    public function test_T024_customer_confirmation_is_a_separate_step_and_expires_safely(): void
    {
        [$booking, $profiles] = $this->scenario(1);

        $wave = $this->dispatch()->openWave($booking, [['profile' => $profiles[0]]]);
        $offer = $wave->offers()->first();

        ['hold' => $hold] = $this->dispatch()->acceptOffer($offer, $profiles[0]);

        // Booking is NOT yet confirmed.
        $this->assertSame('awaiting_customer_confirmation', $booking->fresh()->state);

        // Expire the hold and try to confirm → must fail and release the provider.
        $hold->update(['expires_at' => now()->subSecond()]);

        try {
            $this->dispatch()->customerConfirm($booking->fresh(), 1);
            $this->fail('confirmation should have been rejected');
        } catch (\DomainException) {
            // expected
        }

        $this->assertSame('customer_confirmation_expired', $booking->fresh()->state);
        $this->assertNull($booking->fresh()->confirmed_at);
        $this->assertSame(0, $booking->holds()->where('status', 'active')->count());
    }

    public function test_customer_confirmation_inside_window_confirms_the_booking(): void
    {
        [$booking, $profiles] = $this->scenario(1);

        $wave = $this->dispatch()->openWave($booking, [['profile' => $profiles[0]]]);
        $offer = $wave->offers()->first();

        $this->dispatch()->acceptOffer($offer, $profiles[0]);

        $confirmed = $this->dispatch()->customerConfirm($booking->fresh(), 1);

        $this->assertSame('confirmed', $confirmed->state);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame($profiles[0]->id, $confirmed->professional_profile_id);
    }

    public function test_ineligible_provider_cannot_accept_even_if_offered(): void
    {
        [$booking, $profiles, $service] = $this->scenario(1);

        $wave = $this->dispatch()->openWave($booking, [['profile' => $profiles[0]]]);
        $offer = $wave->offers()->first();

        // Suspend the provider after the offer was already sent.
        $profiles[0]->update(['status' => 'suspended']);

        $this->expectException(OfferNotAvailableException::class);
        $this->dispatch()->acceptOffer($offer, $profiles[0]->fresh());
    }
}