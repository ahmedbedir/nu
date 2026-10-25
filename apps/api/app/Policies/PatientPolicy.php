<?php

namespace App\Policies;

use App\Domain\Patients\Services\PatientAccess;
use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function __construct(private readonly PatientAccess $access) {}

    public function viewAny(User $user): bool
    {
        // Any authenticated account may list *its own* patients; the query is scoped.
        return true;
    }

    public function view(User $user, Patient $patient): bool
    {
        return $this->access->canViewSummary($user, $patient);
    }

    public function viewMedical(User $user, Patient $patient): bool
    {
        return $this->access->canViewMedical($user, $patient);
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->access->isLinked($user, $patient);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $this->access->isLinked($user, $patient);
    }

    public function book(User $user, Patient $patient): bool
    {
        return $this->access->canBook($user, $patient);
    }

    public function manageDelegations(User $user, Patient $patient): bool
    {
        return $this->access->canManageDelegations($user, $patient);
    }
}