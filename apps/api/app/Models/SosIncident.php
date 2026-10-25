<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SOS incident (PRD-08, #176–#184).
 *
 * State is honest by construction: an incident that never reached the server is
 * `initiated_local` and must never be presented as acknowledged (T-058).
 */
class SosIncident extends Model
{
    use HasPublicId;

    protected $fillable = [
        'booking_id', 'user_id', 'professional_profile_id', 'reporter_role', 'state',
        'latitude', 'longitude', 'accuracy_m', 'occurred_at', 'contact_phone_ciphertext',
        'acked_at', 'acked_by', 'closed_at', 'closed_by', 'closure_reason', 'closure_notes',
    ];

    protected $hidden = ['contact_phone_ciphertext'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'acked_at' => 'datetime',
            'closed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(SosDeliveryAttempt::class);
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(SosAcknowledgement::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(SosEscalation::class);
    }

    public function isAcknowledged(): bool
    {
        return $this->acked_at !== null;
    }
}