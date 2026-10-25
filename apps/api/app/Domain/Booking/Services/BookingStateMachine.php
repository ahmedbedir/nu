<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Exceptions\BookingConflictException;

/**
 * Booking state machine (PRD §3). Invalid transitions are rejected server-side
 * and every accepted transition is written to `hc_booking_events`.
 */
class BookingStateMachine
{
    public const TRANSITIONS = [
        'draft' => ['eligibility_check', 'cancelled_customer'],
        'eligibility_check' => ['search_pending', 'eligibility_blocked', 'clinical_review_pending'],
        'search_pending' => ['searching', 'no_match', 'cancelled_customer'],
        'searching' => ['offered', 'no_match', 'cancelled_customer'],
        'offered' => ['provider_provisionally_accepted', 'offer_expired', 'no_match', 'cancelled_customer'],
        'provider_provisionally_accepted' => ['awaiting_customer_confirmation', 'offer_expired', 'cancelled_provider', 'cancelled_customer'],
        'awaiting_customer_confirmation' => ['confirmed', 'customer_confirmation_expired', 'cancelled_customer'],
        'confirmed' => ['scheduled', 'cancelled_customer', 'cancelled_provider', 'reassignment_pending'],
        'scheduled' => ['preparing', 'cancelled_customer', 'cancelled_provider', 'reassignment_pending'],
        'preparing' => ['departing', 'cancelled_customer', 'cancelled_provider'],
        'departing' => ['en_route', 'cancelled_customer', 'no_show'],
        'en_route' => ['arrival_claimed', 'no_show', 'under_investigation'],
        'arrival_claimed' => ['arrival_confirmed', 'under_investigation'],
        'arrival_confirmed' => ['in_session'],
        'in_session' => ['provider_completed', 'aborted_unsafe', 'under_investigation'],
        'provider_completed' => ['customer_review_pending'],
        'customer_review_pending' => ['invoiced', 'disputed'],
        'invoiced' => ['settled', 'disputed'],
        'disputed' => ['settled', 'under_investigation'],
        'settled' => ['closed'],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw BookingConflictException::notReady("{$from} → {$to}");
        }
    }

    public function isTerminal(string $state): bool
    {
        return in_array($state, [
            'closed', 'cancelled_customer', 'cancelled_provider', 'no_show',
            'eligibility_blocked', 'aborted_unsafe', 'no_match',
        ], true);
    }
}