<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingRequest extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'customer_user_id', 'patient_id', 'visit_address_id', 'service_id',
        'required_gender', 'gender_consent_for_alternate', 'mode',
        'chosen_professional_id', 'state', 'form_answers',
        'booking_device_lat', 'booking_device_lng', 'requested_for',
    ];

    protected function casts(): array
    {
        return [
            'form_answers' => 'array',
            'gender_consent_for_alternate' => 'boolean',
            'booking_device_lat' => 'decimal:7',
            'booking_device_lng' => 'decimal:7',
            'requested_for' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitAddress(): BelongsTo
    {
        return $this->belongsTo(PatientAddress::class, 'visit_address_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}