<?php

namespace App\Domain\Booking\Exceptions;

/** A valid request cannot proceed because the booking's current state conflicts. */
class BookingConflictException extends \DomainException
{
    public function __construct(
        public readonly string $codeName,
        string $message = 'الحجز غير جاهز لهذه العملية',
    ) {
        parent::__construct($message);
    }

    public static function notReady(string $state): self
    {
        return new self(
            'booking_not_ready',
            'الحجز في حالة لا تسمح بهذه العملية: '.$state,
        );
    }

    public static function quoteExpired(): self
    {
        return new self('quote_expired', 'انتهت مهلة السعر المؤقت، يرجى إنشاء عرض جديد');
    }

    public static function holdExpired(): self
    {
        return new self('confirmation_expired', 'انتهت مهلة التأكيد، يرجى إعادة المحاولة');
    }
}