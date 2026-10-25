<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The VISIT destination (#105).
 *
 * This is deliberately a separate object from the booking device's GPS. A son may
 * book from Cairo for a parent in Alexandria: dispatch must target this verified pin,
 * never the booker's phone position.
 */
class PatientAddress extends Model
{
    use HasPublicId;

    protected $fillable = [
        'patient_id', 'region_id', 'label', 'address_line', 'building', 'floor',
        'apartment', 'landmark', 'latitude', 'longitude', 'accuracy_m',
        'geocode_source', 'verification_status', 'verified_at', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_m' => 'decimal:2',
            'verified_at' => 'datetime',
            'is_default' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}