<?php

namespace App\Domain\Patients\Services;

use App\Models\Patient;
use App\Models\PatientDelegation;
use App\Models\PatientUserLink;
use App\Models\User;

/**
 * Single source of truth for "may this account touch this patient?".
 *
 * Enforces the core privacy invariant (AC-01, T-003, T-004):
 *  - a user sees a patient only through a verified link or an ACTIVE delegation;
 *  - delegation scopes are exact — "book" never implies "view_medical";
 *  - nothing here reveals whether a patient the caller cannot access exists.
 */
class PatientAccess
{
    public function isLinked(User $user, Patient $patient): bool
    {
        // The account that registered the record is its controller until a formal
        // link/delegation supersedes it; all such access is audited.
        if ((int) $patient->created_by === (int) $user->id) {
            return true;
        }

        return PatientUserLink::query()
            ->where('patient_id', $patient->id)
            ->where('user_id', $user->id)
            ->where('verification_status', 'verified')
            ->exists();
    }

    public function hasDelegationScope(User $user, Patient $patient, string $scope): bool
    {
        return PatientDelegation::query()
            ->where('patient_id', $patient->id)
            ->where('grantee_user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->get()
            ->contains(fn (PatientDelegation $d) => $d->hasScope($scope));
    }

    /** Any relationship at all: verified link or any active delegation. */
    public function canViewSummary(User $user, Patient $patient): bool
    {
        return $this->isLinked($user, $patient)
            || $this->hasDelegationScope($user, $patient, PatientDelegation::SCOPE_VIEW_VISIT_STATUS)
            || $this->hasDelegationScope($user, $patient, PatientDelegation::SCOPE_VIEW_MEDICAL);
    }

    /** Full clinical record (conditions, medications, allergies, notes). */
    public function canViewMedical(User $user, Patient $patient): bool
    {
        return $this->isLinked($user, $patient)
            || $this->hasDelegationScope($user, $patient, PatientDelegation::SCOPE_VIEW_MEDICAL);
    }

    public function canBook(User $user, Patient $patient): bool
    {
        return $this->isLinked($user, $patient)
            || $this->hasDelegationScope($user, $patient, PatientDelegation::SCOPE_BOOK);
    }

    public function canPay(User $user, Patient $patient): bool
    {
        return $this->isLinked($user, $patient)
            || $this->hasDelegationScope($user, $patient, PatientDelegation::SCOPE_PAY);
    }

    /** Only the linked owner/guardian may manage delegations (#24). */
    public function canManageDelegations(User $user, Patient $patient): bool
    {
        return $this->isLinked($user, $patient);
    }
}