<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\OfferNotAvailableException;
use App\Domain\Booking\Services\BookingStateMachine;
use App\Domain\Compliance\Services\AuditLogger;
use App\Domain\Professionals\Services\ProviderEligibility;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingHold;
use App\Models\DispatchOffer;
use App\Models\DispatchWave;
use App\Models\ProfessionalProfile;
use App\Models\SchedulingReservation;
use Illuminate\Support\Facades\DB;

/**
 * Instant dispatch: waves, offers with a 30s TTL, and the single-winner award.
 *
 * Guarantees:
 *  - an offer can only be accepted while it is still open and unexpired (T-022);
 *  - exactly ONE provider can win a booking, even under concurrent accepts (T-023).
 *    Enforced by locking the booking row (`FOR UPDATE`) and relying on the unique
 *    index over `booking_holds.active_key`;
 *  - a provider with overlapping confirmed/held time cannot be awarded (T-025);
 *  - provisional acceptance does NOT confirm the booking: the customer must
 *    separately confirm the quote (T-024).
 */
class DispatchService
{
    public function __construct(
        private readonly ProviderEligibility $eligibility,
        private readonly BookingStateMachine $states,
        private readonly AuditLogger $audit,
    ) {}

    /** Open a new dispatch wave and create offers for the eligible candidates. */
    public function openWave(Booking $booking, array $candidates, int $waveNumber = 1): DispatchWave
    {
        $ttl = (int) config('homecare.dispatch.offer_ttl_seconds');
        $radius = (int) config('homecare.dispatch.initial_radius_km');

        return DB::transaction(function () use ($booking, $candidates, $waveNumber, $ttl, $radius) {
            $wave = DispatchWave::create([
                'booking_id' => $booking->id,
                'wave_number' => $waveNumber,
                'radius_km' => $radius,
                'offer_ttl_seconds' => $ttl,
                'status' => 'open',
                'expires_at' => now()->addSeconds($ttl),
            ]);

            foreach ($candidates as $candidate) {
                /** @var array{profile: ProfessionalProfile, eta_seconds?: int, distance_m?: int} $candidate */
                $profile = $candidate['profile'];

                DispatchOffer::updateOrCreate(
                    ['booking_id' => $booking->id, 'professional_profile_id' => $profile->id],
                    [
                        'wave_id' => $wave->id,
                        'status' => 'offered',
                        'eta_seconds' => $candidate['eta_seconds'] ?? null,
                        'distance_m' => $candidate['distance_m'] ?? null,
                        'expires_at' => now()->addSeconds($ttl),
                    ],
                );
            }

            $this->transition($booking, 'searching', null, ['wave' => $waveNumber]);

            return $wave;
        });
    }

    /**
     * Provider provisionally accepts an offer.
     *
     * @return array{booking: Booking, hold: BookingHold}
     *
     * @throws OfferNotAvailableException when the offer is gone, expired or already won
     */
    public function acceptOffer(DispatchOffer $offer, ProfessionalProfile $profile): array
    {
        return DB::transaction(function () use ($offer, $profile) {
            // Lock the booking first: this is the serialisation point for all acceptors.
            $booking = Booking::query()->whereKey($offer->booking_id)->lockForUpdate()->firstOrFail();

            // Re-read the offer inside the lock so a concurrent winner is visible.
            $fresh = DispatchOffer::query()->whereKey($offer->id)->firstOrFail();

            if ((int) $fresh->professional_profile_id !== (int) $profile->id) {
                throw new OfferNotAvailableException('offer_not_owned');
            }

            if ($fresh->status !== 'offered') {
                throw new OfferNotAvailableException('offer_already_answered');
            }

            if ($fresh->isExpired()) {
                $fresh->update(['status' => 'expired']);

                throw new OfferNotAvailableException('offer_expired');
            }

            if ($booking->state !== 'searching' && $booking->state !== 'offered') {
                throw new OfferNotAvailableException('booking_no_longer_open');
            }

            // A booking that already has an active hold has a winner.
            if ($booking->activeHold()->exists()) {
                throw new OfferNotAvailableException('booking_already_awarded');
            }

            $service = $booking->request->service;

            if (! $this->eligibility->isEligible($profile, $service)) {
                throw new OfferNotAvailableException('provider_not_eligible');
            }

            if ($this->hasOverlappingCommitment($profile, $booking)) {
                throw new OfferNotAvailableException('schedule_conflict');
            }

            $holdTtl = (int) config('homecare.dispatch.quote_hold_ttl_seconds');

            // The UNIQUE index on active_key is the hard guarantee: a second winner
            // hits a duplicate-key error instead of silently double-awarding.
            $hold = BookingHold::create([
                'booking_id' => $booking->id,
                'professional_profile_id' => $profile->id,
                'status' => 'active',
                'expires_at' => now()->addSeconds($holdTtl),
            ]);

            $fresh->update(['status' => 'accepted', 'responded_at' => now()]);
            $booking->update([
                'professional_profile_id' => $profile->id,
                'state' => 'awaiting_customer_confirmation',
            ]);

            SchedulingReservation::create([
                'booking_id' => $booking->id,
                'professional_profile_id' => $profile->id,
                'starts_at' => $booking->scheduled_for ?? now()->addHour(),
                'ends_at' => ($booking->scheduled_for ?? now()->addHour())->copy()->addMinutes($service->default_duration_minutes),
                'status' => 'held',
            ]);

            BookingEvent::create([
                'booking_id' => $booking->id,
                'event' => 'provider_provisionally_accepted',
                'from_state' => 'searching',
                'to_state' => 'awaiting_customer_confirmation',
                'payload' => ['professional_profile_id' => $profile->id],
            ]);

            $this->audit->log('booking.hold_created', null, 'booking', $booking->public_id, 'dispatch');

            return ['booking' => $booking->fresh(), 'hold' => $hold];
        });
    }

    /** Customer separately confirms the quote — only now is the booking confirmed. */
    public function customerConfirm(Booking $booking, int $quoteId): Booking
    {
        // The state change for an expired hold must COMMIT, then the caller is told.
        // Throwing inside the transaction would roll the release back.
        $result = DB::transaction(function () use ($booking, $quoteId) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($locked->state !== 'awaiting_customer_confirmation') {
                return ['error' => 'invalid_state'];
            }

            $hold = $locked->activeHold()->first();

            if (! $hold || $hold->isExpired()) {
                $hold?->update(['status' => 'expired', 'release_reason' => 'customer_confirmation_expired']);

                $locked->update(['state' => 'customer_confirmation_expired']);

                BookingEvent::create([
                    'booking_id' => $locked->id,
                    'event' => 'customer_confirmation_expired',
                    'from_state' => 'awaiting_customer_confirmation',
                    'to_state' => 'customer_confirmation_expired',
                ]);

                return ['error' => 'expired'];
            }

            $this->states->assertTransition('awaiting_customer_confirmation', 'confirmed');

            $hold->update(['status' => 'consumed']);
            $locked->update(['state' => 'confirmed', 'confirmed_at' => now()]);

            BookingEvent::create([
                'booking_id' => $locked->id,
                'event' => 'customer_confirmed',
                'from_state' => 'awaiting_customer_confirmation',
                'to_state' => 'confirmed',
                'payload' => ['quote_id' => $quoteId],
            ]);

            return ['booking' => $locked->fresh()];
        });

        return match ($result['error'] ?? null) {
            'expired' => throw new \DomainException('انتهت مهلة التأكيد، يرجى إعادة المحاولة'),
            'invalid_state' => throw new \DomainException('لا يمكن تأكيد الحجز في حالته الحالية'),
            default => $result['booking'],
        };
    }

    /** Does this provider already hold/confirm time that overlaps the target slot? */
    private function hasOverlappingCommitment(ProfessionalProfile $profile, Booking $booking): bool
    {
        $service = $booking->request->service;
        $start = $booking->scheduled_for ?? now()->addHour();
        $end = $start->copy()->addMinutes($service->default_duration_minutes);

        // Travel/rest buffer is part of the block (decision #125).
        $bufferMinutes = 30;
        $blockStart = $start->copy()->subMinutes($bufferMinutes);
        $blockEnd = $end->copy()->addMinutes($bufferMinutes);

        return SchedulingReservation::query()
            ->where('professional_profile_id', $profile->id)
            ->whereIn('status', ['held', 'confirmed'])
            ->where('starts_at', '<', $blockEnd)
            ->where('ends_at', '>', $blockStart)
            ->exists();
    }

    private function transition(Booking $booking, string $to, ?int $actorId = null, array $payload = []): void
    {
        $from = $booking->state;

        if ($from !== $to) {
            $this->states->assertTransition($from, $to);
        }

        $booking->update(['state' => $to]);

        BookingEvent::create([
            'booking_id' => $booking->id,
            'event' => 'state_changed',
            'from_state' => $from,
            'to_state' => $to,
            'actor_user_id' => $actorId,
            'payload' => $payload,
        ]);
    }
}