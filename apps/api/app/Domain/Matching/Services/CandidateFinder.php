<?php

namespace App\Domain\Matching\Services;

use App\Domain\Geo\Contracts\GeoDistance;
use App\Domain\Professionals\Services\ProviderEligibility;
use App\Models\Booking;
use App\Models\ProfessionalProfile;
use App\Models\ProviderPresence;
use App\Models\Service;

/**
 * Hard eligibility filter for instant dispatch. Ranking is applied only after
 * these filters; no rating, AI score or proximity can bypass one of them.
 */
class CandidateFinder
{
    public function __construct(
        private readonly ProviderEligibility $eligibility,
        private readonly GeoDistance $geo,
    ) {}

    /** @return array<int, array{profile: ProfessionalProfile, distance_m: int, eta_seconds: int}> */
    public function find(Booking $booking, int $radiusKm = 10): array
    {
        $booking->loadMissing(['request.service', 'visitAddress']);
        $request = $booking->request;
        $service = $request->service;
        $address = $booking->visitAddress;

        if (! $address || ! $address->hasCoordinates()) {
            return [];
        }

        $profiles = ProfessionalProfile::query()
            ->where('status', 'active')
            ->where('profession_id', $service->profession_id)
            ->with(['credentials', 'serviceApprovals', 'profession'])
            ->get();

        $candidates = [];

        foreach ($profiles as $profile) {
            if ($request->required_gender !== null && $profile->gender !== $request->required_gender) {
                if (! $request->gender_consent_for_alternate) {
                    continue;
                }
            }

            $check = $this->eligibility->check($profile, $service);
            if (! $check['eligible']) {
                continue;
            }

            $presence = ProviderPresence::query()
                ->where('professional_profile_id', $profile->id)
                ->latest('captured_at')
                ->first();

            if (! $presence?->hasFreshLocation((int) config('homecare.dispatch.gps_freshness_seconds', 120))) {
                continue;
            }

            $distance = $this->geo->metres(
                (float) $address->latitude,
                (float) $address->longitude,
                (float) $presence->latitude,
                (float) $presence->longitude,
            );

            if ($distance > $radiusKm * 1000) {
                continue;
            }

            $eta = $this->geo->travelEstimate(
                (float) $presence->latitude,
                (float) $presence->longitude,
                (float) $address->latitude,
                (float) $address->longitude,
            );

            $candidates[] = [
                'profile' => $profile,
                'distance_m' => (int) round($distance),
                'eta_seconds' => $eta['seconds'],
            ];
        }

        usort($candidates, fn (array $a, array $b) => $a['eta_seconds'] <=> $b['eta_seconds']);

        return $candidates;
    }
}