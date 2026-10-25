<?php

namespace App\Domain\Clinical\Services;

use App\Models\Patient;
use App\Models\Service;

/**
 * Deterministic clinical screening (PRD-03, decisions #78–#82).
 *
 * Outcomes:
 *  - `eligible`                 — booking may proceed normally;
 *  - `requires_information`     — allowed to book, but safety data must be completed
 *                                 before any procedure is executed (#80);
 *  - `requires_qualified_review`— a qualified human must decide before execution;
 *  - `rejected_red_flag`        — conventional booking stops; emergency guidance shown.
 *
 * This class contains **no AI and no generated clinical advice**. Rules are the
 * approved, stored `red_flag_screen` rules. An UNKNOWN answer is never treated as
 * a "no": it blocks execution, not the booking itself.
 */
class EligibilityScreening
{
    public const ELIGIBLE = 'eligible';
    public const REQUIRES_INFORMATION = 'requires_information';
    public const REQUIRES_REVIEW = 'requires_qualified_review';
    public const REJECTED_RED_FLAG = 'rejected_red_flag';

    /**
     * @param  array<string, mixed>  $answers
     * @return array{outcome: string, reasons: string[], missing: string[], guidance: ?string}
     */
    public function screen(Service $service, Patient $patient, array $answers): array
    {
        $reasons = [];
        $missing = [];

        // 1) Mandatory safety fields must be answered; UNKNOWN is an explicit value
        //    but it still blocks execution, so it is reported as a gap.
        foreach ($service->required_fields ?? [] as $field) {
            $key = is_array($field) ? ($field['key'] ?? null) : $field;

            if (! $key) {
                continue;
            }

            if (! array_key_exists($key, $answers) || $answers[$key] === null || $answers[$key] === '') {
                $missing[] = $key;
            } elseif (is_string($answers[$key]) && strtoupper($answers[$key]) === 'UNKNOWN') {
                $missing[] = $key;
            }
        }

        // 2) Approved red-flag rules. Each rule is {field, op, value, message_ar, message_en}.
        foreach ($service->red_flag_screen ?? [] as $rule) {
            if (! is_array($rule) || ! isset($rule['field'], $rule['op'])) {
                continue;
            }

            $answer = $answers[$rule['field']] ?? null;

            if ($answer === null) {
                continue; // absence is handled by the required-field pass
            }

            if ($this->matches($answer, (string) $rule['op'], $rule['value'] ?? null)) {
                $reasons[] = (string) ($rule['code'] ?? 'red_flag');

                // A hard red flag stops the conventional flow entirely.
                if (($rule['severity'] ?? 'hard') === 'hard') {
                    return [
                        'outcome' => self::REJECTED_RED_FLAG,
                        'reasons' => array_values(array_unique($reasons)),
                        'missing' => array_values(array_unique($missing)),
                        'guidance' => 'red_flag_emergency_guidance',
                    ];
                }
            }
        }

        // 3) Age suitability is a hard gate for the service.
        $age = $patient->age();
        if (! $service->ageAllowedFor($age)) {
            return [
                'outcome' => self::REJECTED_RED_FLAG,
                'reasons' => ['age_outside_service_range'],
                'missing' => array_values(array_unique($missing)),
                'guidance' => 'service_not_suitable_for_age',
            ];
        }

        if ($reasons !== []) {
            return [
                'outcome' => self::REQUIRES_REVIEW,
                'reasons' => array_values(array_unique($reasons)),
                'missing' => array_values(array_unique($missing)),
                'guidance' => 'qualified_review_required',
            ];
        }

        if ($missing !== []) {
            return [
                'outcome' => self::REQUIRES_INFORMATION,
                'reasons' => ['safety_information_missing'],
                'missing' => array_values(array_unique($missing)),
                'guidance' => 'provider_must_complete_before_execution',
            ];
        }

        return [
            'outcome' => self::ELIGIBLE,
            'reasons' => [],
            'missing' => [],
            'guidance' => null,
        ];
    }

    /** Only `eligible` may proceed to dispatch without a human decision. */
    public function canDispatch(array $result): bool
    {
        return $result['outcome'] === self::ELIGIBLE;
    }

    private function matches(mixed $answer, string $op, mixed $expected): bool
    {
        return match ($op) {
            'eq' => (string) $answer === (string) $expected,
            'neq' => (string) $answer !== (string) $expected,
            'gt' => is_numeric($answer) && is_numeric($expected) && (float) $answer > (float) $expected,
            'gte' => is_numeric($answer) && is_numeric($expected) && (float) $answer >= (float) $expected,
            'lt' => is_numeric($answer) && is_numeric($expected) && (float) $answer < (float) $expected,
            'lte' => is_numeric($answer) && is_numeric($expected) && (float) $answer <= (float) $expected,
            'in' => is_array($expected) && in_array((string) $answer, array_map('strval', $expected), true),
            'true' => in_array($answer, [true, 1, '1', 'yes', 'true'], true),
            default => false,
        };
    }
}