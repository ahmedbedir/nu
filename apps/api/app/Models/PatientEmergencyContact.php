<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientEmergencyContact extends Model
{
    public const MAX_CONTACTS = 3;

    protected $fillable = ['patient_id', 'name', 'relationship', 'phone', 'priority'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}