<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchWave extends Model
{
    use HasPublicId;

    protected $fillable = [
        'booking_id', 'wave_number', 'radius_km', 'offer_ttl_seconds', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DispatchOffer::class, 'wave_id');
    }
}