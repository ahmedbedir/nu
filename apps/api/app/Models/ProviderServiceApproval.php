<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderServiceApproval extends Model
{
    protected $fillable = [
        'professional_profile_id', 'service_code', 'status',
        'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'approved';
    }
}