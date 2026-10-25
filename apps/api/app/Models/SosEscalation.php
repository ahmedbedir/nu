<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosEscalation extends Model
{
    public $timestamps = false;

    protected $fillable = ['sos_incident_id', 'level', 'escalated_to', 'channel', 'escalated_at', 'acknowledged_at', 'status'];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SosIncident::class, 'sos_incident_id');
    }
}