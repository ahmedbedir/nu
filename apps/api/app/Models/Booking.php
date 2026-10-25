<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'booking_request_id', 'customer_user_id', 'patient_id', 'visit_address_id',
        'professional_profile_id', 'state', 'confirmed_at', 'scheduled_for',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class, 'booking_request_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitAddress(): BelongsTo
    {
        return $this->belongsTo(PatientAddress::class, 'visit_address_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function waves(): HasMany
    {
        return $this->hasMany(DispatchWave::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DispatchOffer::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function activeHold(): HasOne
    {
        return $this->hasOne(BookingHold::class)->where('status', 'active');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(BookingQuote::class);
    }

    public function latestQuote(): HasOne
    {
        return $this->hasOne(BookingQuote::class)->latestOfMany('version');
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }
}