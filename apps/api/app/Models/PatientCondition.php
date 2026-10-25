<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCondition extends Model
{
    protected $fillable = ['patient_id', 'name', 'code', 'status', 'source', 'recorded_by'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}