<?php

namespace App\Domain\Booking\Exceptions;

use RuntimeException;

class OfferNotAvailableException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Offer not available: '.$reason);
    }
}