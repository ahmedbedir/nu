<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patient is a distinct clinical identity. It is never the same object as the
 * booking account owner, a delegate, or a visit address.
 *
 * Unknown clinical data is recorded explicitly through the per-domain *_state
 * columns (#80) rather than being fabricated.
 */
class Patient extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'full_name', 'birth_date', 'sex', 'national_id_ciphertext', 'blood_type',
        'phone', 'conditions_state', 'medications_state', 'allergies_state',
        'clinical_notes', 'status', 'created_by',
    ];

    protected $hidden = ['national_id_ciphertext'];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function links(): HasMany
    {
        return $this->hasMany(PatientUserLink::class);
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(PatientDelegation::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(PatientMedication::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(PatientEmergencyContact::class)->orderBy('priority');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class);
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }
}