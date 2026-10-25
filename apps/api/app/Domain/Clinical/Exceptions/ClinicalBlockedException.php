<?php

namespace App\Domain\Clinical\Exceptions;

/**
 * A clinical safety gate stopped the request.
 *
 * Carries the deterministic screening outcome so the client can render the right
 * screen (emergency guidance, "we need more information", or "under review")
 * instead of a generic error. Extends \DomainException for compatibility.
 */
class ClinicalBlockedException extends \DomainException
{
    /**
     * @param  string[]  $reasons
     * @param  string[]  $missing
     */
    public function __construct(
        public readonly string $outcome,
        public readonly array $reasons = [],
        public readonly array $missing = [],
        public readonly ?string $guidance = null,
        string $message = 'تم إيقاف الطلب وفق قواعد السلامة السريرية',
    ) {
        parent::__construct($message);
    }

    public static function redFlag(array $reasons, array $missing = [], ?string $guidance = 'red_flag_emergency_guidance'): self
    {
        return new self(
            'rejected_red_flag',
            $reasons,
            $missing,
            $guidance,
            'هذه الحالة لا تناسب الحجز العادي. يرجى اتباع إرشادات الطوارئ.',
        );
    }

    public static function requiresInformation(array $missing): self
    {
        return new self(
            'requires_information',
            ['safety_information_missing'],
            $missing,
            'provider_must_complete_before_execution',
            'معلومات السلامة غير مكتملة. يمكن حفظ الطلب، لكن لا يمكن تنفيذه قبل استكمالها.',
        );
    }

    public static function requiresReview(array $reasons): self
    {
        return new self(
            'requires_qualified_review',
            $reasons,
            [],
            'qualified_review_required',
            'يحتاج الطلب إلى مراجعة مؤهلة قبل المتابعة.',
        );
    }
}