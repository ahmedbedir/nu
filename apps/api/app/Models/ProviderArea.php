<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderArea extends Model
{
    protected $fillable = ['professional_profile_id', 'region_id', 'radius_km', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}