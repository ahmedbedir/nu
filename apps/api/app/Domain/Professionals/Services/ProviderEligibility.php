<?php

namespace App\Domain\Professionals\Services;

use App\Models\ProfessionalProfile;
use App\Models\Service;

/**
 * Deterministic eligibility gate — the single authority on whether a professional
 * may be offered or may execute a specific service.
 *
 * Hard rules (AC-02, T-011, T-012, T-017):
 *  - profile must be active and not suspended;
 *  - at least one VALID (verified & unexpired) credential must exist;
 *  - the service's profession must match the profile's profession;
 *  - an explicit, approved service-level permission must exist for the service code.
 *
 * No rating, GPS proximity, administrator override or AI score can bypass these.
 * The AI assistant may only ever *suggest*; this class decides.
 */
class ProviderEligibility
{
    /** @return array{eligible: bool, reasons: string[]} */
    public function check(ProfessionalProfile $profile, Service $service): array
    {
        $reasons = [];

        if ($profile->status !== 'active') {
            $reasons[] = 'profile_not_active';
        }

        if ($profile->suspended_until !== null && $profile->suspended_until->isFuture()) {
            $reasons[] = 'profile_suspended';
        }

        if ($service->status !== 'published') {
            $reasons[] = 'service_not_published';
        }

        if ((int) $service->profession_id !== (int) $profile->profession_id) {
            $reasons[] = 'profession_mismatch';
        }

        // Baseline: every active professional needs at least one valid licence.
        if (! $profile->validCredentials()->exists()) {
            $reasons[] = 'no_valid_credential';
        }

        // Service-level scope: verified credential ≠ approved to execute a procedure.
        $approved = $profile->serviceApprovals()
            ->where('service_code', $service->code)
            ->where('status', 'approved')
            ->exists();

        if (! $approved) {
            $reasons[] = 'service_not_approved_for_provider';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function isEligible(ProfessionalProfile $profile, Service $service): bool
    {
        return $this->check($profile, $service)['eligible'];
    }

    /** Paediatric services additionally require a documented relevant qualification (#17/T-017). */
    public function paediatricQualified(ProfessionalProfile $profile): bool
    {
        return $profile->credentials()
            ->where('status', 'verified')
            ->where('credential_type', 'speciality_cert')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->exists();
    }
}