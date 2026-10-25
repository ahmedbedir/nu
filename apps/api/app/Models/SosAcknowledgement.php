<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['sos_incident_id', 'acknowledged_by', 'channel', 'acknowledged_at', 'notes'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SosIncident::class, 'sos_incident_id');
    }
}