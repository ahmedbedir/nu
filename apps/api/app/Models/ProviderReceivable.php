<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money a professional owes the platform (chiefly cash commission).
 * Positive amount = provider owes the platform.
 */
class ProviderReceivable extends Model
{
    protected $fillable = [
        'professional_profile_id', 'amount', 'currency', 'kind',
        'status', 'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }
}