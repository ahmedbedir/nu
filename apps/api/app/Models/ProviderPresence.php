<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPresence extends Model
{
    // The schema intentionally uses the singular physical name `hc_provider_presence`.
    protected $table = 'provider_presence';

    protected $fillable = [
        'professional_profile_id', 'status', 'latitude', 'longitude', 'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    /** Instant dispatch requires fresh GPS (decisions #118–#119). */
    public function hasFreshLocation(int $maxAgeSeconds = 120): bool
    {
        return $this->status === 'online'
            && $this->latitude !== null
            && $this->longitude !== null
            && $this->captured_at !== null
            && $this->captured_at->diffInSeconds(now()) <= $maxAgeSeconds;
    }
}