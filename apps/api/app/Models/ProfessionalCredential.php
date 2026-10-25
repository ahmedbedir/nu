<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalCredential extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'professional_profile_id', 'credential_type', 'title', 'issuing_body',
        'number_ciphertext', 'issued_at', 'expires_at', 'document_ref',
        'status', 'verification_notes',
    ];

    protected $hidden = ['number_ciphertext'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_profile_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CredentialReview::class, 'credential_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && ! $this->isExpired();
    }
}