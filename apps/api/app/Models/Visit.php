<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live visit.
 *
 * `session_started_at` is set ONLY by a verified two-sided check-in. GPS proximity
 * is stored in `geofence_observations` and must never set it (T-030).
 */
class Visit extends Model
{
    use HasPublicId;

    protected $fillable = [
        'booking_id', 'professional_profile_id', 'state', 'departure_eta', 'departed_at',
        'arrival_claimed_at', 'arrival_confirmed_at', 'session_started_at', 'session_ended_at',
        'billed_minutes', 'checkin_evidence', 'geofence_observations',
    ];

    protected function casts(): array
    {
        return [
            'departure_eta' => 'datetime',
            'departed_at' => 'datetime',
            'arrival_claimed_at' => 'datetime',
            'arrival_confirmed_at' => 'datetime',
            'session_started_at' => 'datetime',
            'session_ended_at' => 'datetime',
            'checkin_evidence' => 'array',
            'geofence_observations' => 'array',
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

    public function checkins(): HasMany
    {
        return $this->hasMany(VisitCheckin::class);
    }

    public function timeEvents(): HasMany
    {
        return $this->hasMany(VisitTimeEvent::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class);
    }

    public function isSessionRunning(): bool
    {
        return $this->session_started_at !== null && $this->session_ended_at === null;
    }
}