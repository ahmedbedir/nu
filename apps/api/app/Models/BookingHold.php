<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A temporary exclusive hold created when a provider provisionally accepts.
 *
 * The unique index on `active_key` (a generated column that is non-null only for
 * the ACTIVE row) is what makes "exactly one winner" true even under concurrency.
 */
class BookingHold extends Model
{
    protected $fillable = [
        'booking_id', 'professional_profile_id', 'status',
        'expires_at', 'release_reason_at', 'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'release_reason_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}