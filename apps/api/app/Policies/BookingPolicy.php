<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        if ((int) $booking->customer_user_id === (int) $user->id) {
            return true;
        }

        return $this->isAssignedProfessional($user, $booking);
    }

    /** Only the booking owner may open searches, confirm quotes or cancel. */
    public function manage(User $user, Booking $booking): bool
    {
        return (int) $booking->customer_user_id === (int) $user->id;
    }

    private function isAssignedProfessional(User $user, Booking $booking): bool
    {
        if (! $booking->professional_profile_id) {
            return false;
        }

        return $booking->professional()
            ->where('user_id', $user->id)
            ->exists();
    }
}