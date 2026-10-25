<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP / authentication
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'ttl_seconds' => (int) env('HC_OTP_TTL_SECONDS', 300),
        'max_attempts' => (int) env('HC_OTP_MAX_ATTEMPTS', 5),
        'length' => 6,
        // Requests per phone per window (rate limiting, decision #16).
        'request_limit_per_hour' => (int) env('HC_OTP_REQUEST_LIMIT', 5),
        'request_window_seconds' => 3600,
        // In local/dev the code is written to the log instead of sending SMS.
        'expose_in_local' => env('HC_OTP_EXPOSE_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dispatch / booking defaults (decisions #104, #109, #123, #133–#139)
    |--------------------------------------------------------------------------
    */
    'dispatch' => [
        'offer_ttl_seconds' => (int) env('HC_OFFER_TTL_SECONDS', 30),
        'initial_radius_km' => (int) env('HC_DISPATCH_RADIUS_KM', 10),
        'quote_hold_ttl_seconds' => (int) env('HC_QUOTE_HOLD_TTL_SECONDS', 120),
    ],

    'booking' => [
        'min_advance_minutes' => (int) env('HC_MIN_ADVANCE_BOOKING_MINUTES', 60),
        'calendar_slot_minutes' => 30,
        'calendar_horizon_days' => (int) env('HC_CALENDAR_HORIZON_DAYS', 30),
        'max_continuous_shift_hours' => (int) env('HC_MAX_CONTINUOUS_SHIFT_HOURS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Money (decision #187, #188)
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'currency' => 'EGP',
        // Time-based services: first 30 minutes are the minimum billed block.
        'time_minimum_minutes' => 30,
        'time_billing_increment_minutes' => 1,

        // Defaults used when no admin price rule overrides them. Illustrative
        // only — real prices require accounting/legal review before launch.
        'callout_fee' => (string) env('HC_CALLOUT_FEE', '50.00'),
        'travel_per_km' => (string) env('HC_TRAVEL_PER_KM', '8.00'),
        'time_minute_rate' => (string) env('HC_TIME_MINUTE_RATE', '4.00'),
        'night_uplift_percent' => (string) env('HC_NIGHT_UPLIFT_PERCENT', '25'),
        'commission_percent' => (string) env('HC_COMMISSION_PERCENT', '15'),
        // Night window is Africa/Cairo local time.
        'night_starts_at' => env('HC_NIGHT_STARTS_AT', '21:00'),
        'night_ends_at' => env('HC_NIGHT_ENDS_AT', '07:00'),
        // Time overrun the customer pre-approves; beyond it needs new consent (#189).
        'overrun_cap_minutes' => (int) env('HC_OVERRUN_CAP_MINUTES', 30),
        'quote_ttl_minutes' => (int) env('HC_QUOTE_TTL_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    */
    'privacy' => [
        'max_emergency_contacts' => 3,
        'audit_chain_enabled' => true,
    ],
];