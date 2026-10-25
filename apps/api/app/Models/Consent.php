<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purpose-specific consent (#235). Consent for one purpose is never implied
 * by consent for another, and is never implied by account ownership.
 */
class Consent extends Model
{
    protected $fillable = [
        'user_id', 'purpose', 'version', 'granted',
        'granted_at', 'revoked_at', 'source', 'evidence_ref',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->granted && $this->revoked_at === null;
    }
}